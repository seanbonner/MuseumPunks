export default {
  layout: "layouts/institution.njk",
  tags: "institutions",
  eleventyComputed: {
    permalink: (data) => `/institution/${data.slug}/`,
    title: (data) => data.name,
  },
};
