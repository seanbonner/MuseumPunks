#!/usr/bin/env node
/**
 * For each punks/{id}.md, fetch the live museumpunks.com page via defuddle,
 * extract the narrative (everything after "V1 held by institution:" and its value),
 * and replace only the body of the .md file. Frontmatter is preserved.
 *
 * Usage:
 *   node scripts/import-narratives.mjs
 *   node scripts/import-narratives.mjs 74 110
 */
import { readdir, readFile, writeFile } from "node:fs/promises";
import { execFile } from "node:child_process";
import { promisify } from "node:util";

const exec = promisify(execFile);
const PUNKS_DIR = new URL("../punks/", import.meta.url);
const BASE_URL = "https://museumpunks.com";

async function defuddleUrl(url) {
  const { stdout } = await exec("defuddle", ["parse", url, "--md"], {
    maxBuffer: 10 * 1024 * 1024,
  });
  return stdout;
}

function extractNarrative(md) {
  const lines = md.split("\n");
  const idx = lines.findIndex((l) => /^V1 held by institution:\s*$/.test(l.trim()));
  if (idx === -1) return null;
  let i = idx + 1;
  while (i < lines.length && lines[i].trim() === "") i++;
  i++;
  while (i < lines.length && lines[i].trim() === "") i++;
  return lines.slice(i).join("\n").trim();
}

async function main() {
  const argIds = process.argv.slice(2);
  const files = (await readdir(PUNKS_DIR)).filter((f) => /^\d+\.md$/.test(f));
  const targets = argIds.length
    ? files.filter((f) => argIds.includes(f.replace(".md", "")))
    : files;

  for (const file of targets) {
    const id = file.replace(".md", "");
    const path = new URL(file, PUNKS_DIR);
    const current = await readFile(path, "utf8");
    const fmMatch = current.match(/^---\n[\s\S]*?\n---\n/);
    if (!fmMatch) {
      console.error(`  ${file}: no frontmatter, skipping`);
      continue;
    }
    try {
      const md = await defuddleUrl(`${BASE_URL}/${id}/`);
      const narrative = extractNarrative(md);
      if (!narrative) {
        console.error(`  ${file}: could not extract narrative`);
        continue;
      }
      await writeFile(path, `${fmMatch[0]}\n${narrative}\n`, "utf8");
      console.log(`  ${file}: ${narrative.length} chars`);
    } catch (err) {
      console.error(`  ${file}: ${err.message}`);
    }
  }
}

main().catch((e) => { console.error(e); process.exit(1); });
