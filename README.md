# Concrete CMS add-on: Macareux CSV User Import & Export

Import users from CSV files and export users as CSV files, with selectable columns.

Concrete Core can export users, but this package lets you choose which columns to include. Import maps CSV columns to user properties, groups, and attributes.

**Requirements:** Concrete CMS 9+. For version 8, use the [legacy package](https://github.com/MacareuxDigital/csv_user_import_export).

## Installation

1. Place the package under `packages/md_csv_user_import_export`.
2. Install (or update) it from **Dashboard → Extend Concrete**.
3. Dashboard pages appear under **Users → Import** and **Users → Export**.

## How to use

### Export

1. Open **Dashboard → Users → Export**.
2. Check the columns to include (core fields, groups, attributes).
3. Optionally enable **Keep mapping** so the next visit remembers your selection.
4. Download the CSV.

Group columns export as `1` (member) or `0` (not a member). Attribute columns use each attribute’s text representation.

### Import

1. Upload a CSV (header row required) into the File Manager.
2. Open **Dashboard → Users → Import** and select that file.
3. On the mapping screen, for each importable property, choose the matching CSV column (or **Ignore**).
4. Optionally enable **Keep mapping** and/or **Delete CSV file after imported**.
5. Run **Import**. Rows are processed in a progressive batch.

Matching users: if a row has a **Username**, the user is looked up by name; otherwise by **Email**. Existing users are updated; missing users are created. Empty mapped cells leave the current value alone.

### Group import

Two modes (can be used together):

| Mode | Mapping | Cell value | Effect |
|------|---------|------------|--------|
| Per-group column | Property = group display name (e.g. Administrators) | `1` or `0` | Join or leave that group |
| By group name | Property = **Assign to Group** or **Unassign from Group** | Exact group name (e.g. `Administrators`) | Join or leave that group |

Rules for name-based assign/unassign:

- One group name per cell only (no comma-separated lists).
- Empty or ignored → membership unchanged.
- Unknown group name → logged and skipped; the rest of the row still imports.

Example CSV for name-based assign:

```csv
Username,Email,Group
alice,alice@example.com,Administrators
bob,bob@example.com,Editors
```

Map `Group` → **Assign to Group**.

Example CSV for per-group columns:

```csv
Username,Email,Administrators,Editors
alice,alice@example.com,1,0
bob,bob@example.com,0,1
```

Map each group property to the matching column.

## Package design

### Architecture

| Piece | Role |
|-------|------|
| `CsvTrait` | Shared list of exportable/importable headers (static fields, `g:{id}` groups, `a:{handle}` attributes) |
| Import controller | Upload → mapping UI → batch of `ImportUserCommand` per row |
| `ImportUserCommandHandler` | Create/update user, then apply groups and attributes |
| Export controller + `Exporter` | Selected columns → streamed CSV download |

Saved column choices live in package file config (`csv.import_columns`, `csv.export_columns`) when **Keep mapping** is on.

### Why two group import modes?

- **Per-group boolean columns** match export output and suit spreadsheets that already have one column per group. Internal handles are `g:{groupID}` so renames of display labels do not break stored mappings keyed by ID.
- **Assign / Unassign by name** suits CSVs with a single “Group” (or similar) column whose *value* is the group name. Export does not emit these fields: one cell cannot represent membership in multiple groups, so export keeps the boolean columns only.

### Other decisions

- **Empty cell = no change** for groups and most fields, so partial CSVs do not wipe data.
- **Attributes** use Concrete’s text-export interfaces (`SimpleTextExportableAttributeInterface` / `MulticolumnTextExportableAttributeInterface`), same idea as core CSV tooling.
- **Missing assign/unassign groups** do not fail the row; they are logged so bulk imports stay resilient.
- **Home folder** import/export is not implemented yet (see ToDo).

## ToDo

- Support export/import home folder

## License

MIT License.
