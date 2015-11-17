# SwotNG database
[![Build Status](https://travis-ci.org/kiler129/SwotNG-database.svg?branch=master)](https://travis-ci.org/kiler129/SwotNG-database)

Did you ever tried implementing educational discounts? If your answer is affirmative you probably came across simple but not-so-trivial problem: which domains should I accept?
[Lee Reilly](https://github.com/leereilly/swot) came with really nice solution called [Swot](https://github.com/leereilly/swot):
> Swot is a community-driven or crowdsourced library for verifying that domain names and email addresses are tied to a legitimate university of college - more specifically, an academic institution providing higher education in tertiary, quaternary or any other kind of post-secondary education in any country in the world.

### What's wrong with Swot than?
Swot started as Ruby gem tied to database of domains. While that idea was nice it created many (not resolved until today) problems:
  * Database tied to one language implementation (partially solved by `data-only` branch which is outdated)
  * Blacklisted domains are listed inside Ruby code
  * Wildcard TLDs/SLDs are listed inside Ruby code
  * Database holds only name of institution

### How to use it?
SwotNG database is backward-compatible with original Swot format. See [Structure description](https://github.com/kiler129/SwotNG-database/blob/master/STRUCTURE.md) for details.
If you're starting new project you should read [usage](https://github.com/kiler129/SwotNG-database/blob/master/USAGE.md) document and follow instruction for your language.

### You're lying! There's PHP here!
Yes, you're correct - test script is written in PHP. That script is there to check new pull-requests. You **don't** need PHP environment.

### Future plans
  * Provide script generating PRs to this repository
  * Add missing details for institutions
  * Add data from new PRs made to original Swot database
  * Provide tools for dumping database in various formats (e.g. SQL)
  * Create simple service for querying database via HTTP
  * Spread idea to the world :)
