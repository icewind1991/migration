# Migration

Allow migrating users between Nextcloud instances

## TODO

- Verify that the target server can receive fed. shares
- Update fed. share sources for fed. shares owned by the migrated user
 - Add api to change fed. share source
 - Warn if fed. share servers don't support changing owner server
- Allow apps to hook into migration
 - Hook just provides migration meta-data (source host, user, pass) no full fledge api is provided
 - Warn about apps that don't support migration
 - Warn about apps not installed on target server