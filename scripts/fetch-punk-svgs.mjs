#!/usr/bin/env node
/**
 * Fetch CryptoPunk SVGs from the on-chain CryptoPunksData contract and write
 * them to images/punks/{id}.svg. Source of IDs: filenames in punks/*.md.
 *
 * Usage:
 *   node scripts/fetch-punk-svgs.mjs           # fetch all
 *   node scripts/fetch-punk-svgs.mjs 74 110    # subset
 */
import { readdir, writeFile, mkdir } from "node:fs/promises";
import { createPublicClient, http, fallback } from "viem";
import { mainnet } from "viem/chains";

const CRYPTOPUNKS_DATA = "0x16F5A35647D6F03D5D3da7b35409D65ba03aF3B2";
const ABI = [
  {
    name: "punkImageSvg",
    type: "function",
    stateMutability: "view",
    inputs: [{ name: "index", type: "uint16" }],
    outputs: [{ name: "svg", type: "string" }],
  },
];
const RPC_URLS = [
  "https://eth.llamarpc.com",
  "https://ethereum-rpc.publicnode.com",
  "https://rpc.ankr.com/eth",
  "https://eth.drpc.org",
];

const PUNKS_DIR = new URL("../punks/", import.meta.url);
const OUT_DIR = new URL("../images/punks/", import.meta.url);

async function collectIdsFromDisk() {
  const files = await readdir(PUNKS_DIR);
  return files
    .filter((f) => /^\d+\.md$/.test(f))
    .map((f) => Number(f.replace(/\.md$/, "")))
    .sort((a, b) => a - b);
}

async function fetchWithRetries(client, id, maxAttempts) {
  let lastErr;
  for (let i = 0; i < maxAttempts; i++) {
    try {
      const raw = await client.readContract({
        address: CRYPTOPUNKS_DATA,
        abi: ABI,
        functionName: "punkImageSvg",
        args: [id],
      });
      const prefix = "data:image/svg+xml;utf8,";
      return raw.startsWith(prefix) ? raw.slice(prefix.length) : raw;
    } catch (err) {
      lastErr = err;
      if (i < maxAttempts - 1) await new Promise((r) => setTimeout(r, 500 * (i + 1)));
    }
  }
  throw lastErr;
}

async function main() {
  const cliIds = process.argv.slice(2).map(Number).filter((n) => Number.isFinite(n));
  const ids = cliIds.length ? cliIds : await collectIdsFromDisk();
  if (ids.length === 0) {
    console.log("No punk IDs found.");
    return;
  }
  await mkdir(OUT_DIR, { recursive: true });

  const client = createPublicClient({
    chain: mainnet,
    transport: fallback(RPC_URLS.map((u) => http(u))),
  });

  console.log(`Fetching ${ids.length} SVG(s)...`);
  const failed = [];
  for (const id of ids) {
    try {
      const svg = await fetchWithRetries(client, id, 3);
      await writeFile(new URL(`${id}.svg`, OUT_DIR), svg, "utf8");
      console.log(`  ${id} → images/punks/${id}.svg (${svg.length} bytes)`);
    } catch (err) {
      console.error(`  ${id} FAILED: ${err.shortMessage || err.message}`);
      failed.push(id);
    }
  }
  if (failed.length) {
    console.log(`\nDone with ${ids.length - failed.length}/${ids.length}. Failed: ${failed.join(", ")}`);
    process.exit(1);
  }
  console.log("Done.");
}

main().catch((e) => { console.error(e); process.exit(1); });
