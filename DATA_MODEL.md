Data Model
==========

## Users
 - username (fenix) [PK]
 - display name †
 - github username

## Groups
 - group id [PK]
 - year
 - users
 - code provider (github, gitlab, etc)
 - project's provider id

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
