# Per-User Help Topics (osTicket plugin)

Restrict which **Help Topics** each end user can see when opening a ticket in the **client portal** (`open.php`). Restrictions apply per **user account**, not per organization.

## Requirements

- osTicket **1.18** or newer (see `ost_version` in `plugin.php`)
- MySQL/MariaDB with InnoDB
- Staff access to the **Users** directory to configure assignments

## Installation

1. Copy the entire `osticket-user-topics` folder into your osTicket **`include/plugins/`** directory.
2. Log in to the **staff control panel**.
3. Go to **Admin → Manage → Plugins**.
4. Find **Per-User Help Topics**, click to **install**, then set status to **Active**.
5. On first activation the plugin creates table `{prefix}user_topic_access` (default: `ost_user_topic_access`).

You can also run `install.sql` manually if needed (adjust the table prefix to match `TABLE_PREFIX` in `ost-config.php`).

## Configuration

1. **Staff panel → Users** (user directory).
2. Open an end user (click their **name**).
3. Open the **Help Topics** tab (next to **Tickets** and **Notes**).
4. Either:
   - Enable **No restriction (all public help topics)** — user sees every public help topic (default when no rows exist in the database), or
   - Disable that option and check the allowed topics, then click **Save**.

### Permissions

| Action | Permission |
|--------|------------|
| View tab | User directory access (`User::PERM_DIRECTORY`) |
| Save assignments | Edit users (`User::PERM_EDIT`) |

## Behavior

- **Client portal:** Logged-in users with assigned topics only see those topics in the `#topicId` dropdown on **Open a New Ticket**.
- **Server-side:** On ticket creation (`ticket.create.before`), a disallowed topic is rejected with: *You are not allowed to select this Help Topic*.
- **Guests** and users with **no rows** in `user_topic_access` are **unrestricted** (all public help topics).
- This plugin does **not** filter which existing **tickets** a user can view—only help topic choice when **creating** tickets via the web portal.

## Uninstall

Disabling the plugin stops enforcement. **Uninstalling** the plugin runs `pre_uninstall` and **drops** `{prefix}user_topic_access` and all assignment data.

## Plugin instances

Hooks register when the plugin is **Active** at the plugin level (`init()`), even if no plugin instance is enabled. A single instance is sufficient if your osTicket setup uses instances.

## License

GNU General Public License v2.0 — see [LICENSE](LICENSE).

## Author

Enrique Vargas
