Data Model
==========

## Students
 - username (fenix) [PK]
 - display name †
 - github username

## Groups
 - group id [PK]
 - year
 - students
 - code provider (github, gitlab, etc)
 - project's provider id
 - project name
 - project description
 - project website
 - CLA required?
 - Major users
 - lines of code
 - coding style (URL)
 - bugs for beginners (URL)
 - project ideas (URL)
 - getting started manual (URL)
 - developer's manual (URL)
 - testing manual (URL)
 - mailing list (URL)
 - patch submission (URL)
 - comments

## Patches
 - patch id [PK]
 - group id
 - PR id
 - status (open, merged, rejected) †
 - number of lines added †
 - number of lines deleted †
 - impacted files †

## Draft Patches
(to be reviewed by TAs before creating a PR)
TODO

† Cached data from 3rd parties; refreshed by a cron job
