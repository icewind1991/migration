# Migration

Allow migrating users between Nextcloud instances

## Usage

### Web interface

Any user can trigger migration for it self from the Personal settings in the web interface

### CLI

An admin can trigger migration for any user with an `occ` command

```
occ migration:migrate targetUser sourceUser@remotecloud.com
```

The password for the remote users will be asked by the `occ` command

## TODO

- Add option to send new fed. shares for existing outgoing fed. shares on the source server
 - Warning that shares have to be re-accepted
- Allow apps to hook into migration
 - Hook just provides migration meta-data (source host, user, pass) no full fledged api is provided
 - Warn about apps that don't support migration
 - Warn about apps not installed on target server
