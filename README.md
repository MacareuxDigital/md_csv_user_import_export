# Concrete CMS add-on: Macareux CSV User Import & Export

This package enables it to export users as CSV files and import users from CSV files.

Concrete Core has a feature to export users, but you can select columns to export with this package.

This package supports Concrete CMS version 9+ only.
You can use the [legacy version](https://github.com/MacareuxDigital/csv_user_import_export) if you're running Concrete CMS version 8.

## `password_reset` column

Import and export both include a `password_reset` column (the CSV header is **Password Reset**). It maps to `Users.uIsPasswordReset`.

Use it when imported users should sign in with a temporary password and then choose their own. On the next successful login with Concrete's own authentication type, core sends them to the required password-change screen. Saving a new password clears the flag.

The cell is read the same way as `validated`: cast to an integer, and a blank cell is ignored.

| Value | New user | Existing user |
| --- | --- | --- |
| `1` | Flag the user so the next Concrete login requires a password change. | Set the flag when it is not already set. |
| `0` | Leave the flag unset. | Clear the flag. |
| empty or column omitted | Leave the flag unset. | Leave the current flag as it is. |

Export writes `1` or `0`, so a file exported from this add-on can be imported again.

## ToDo

Support export/import home folder

## License

MIT License.