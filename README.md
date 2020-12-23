# slack-work-credit-plus

PHP-based Slack app and web interface to facilitate the use of Slack accounts for managing work credit and various other operations relating to a non-profit housing cooperative.

This is currently in use at RCHC and may be generalized in the future for use at other organizations.

This document is extremely WIP and subject to change over time.

---

### Database Structure

Below is a brief overview of the MySQL database tables and how they fit together to support this app.

**event_logs**: For debugging various automated (hour debits scheduled) or triggered (email sent/failed to send) events. Also, contains any deleted time records, so they can still be referenced.

**sl_committees**: Lookup table for committees (identified by slack_group_id). The slack_handle column is what you would use to @ a group of users in Slack (e.g. @membership).

**sl_config**: Contains various tokens and other configuration values that the system needs to run. These should rarely need to be modified.

**sl_houses**: Lookup table for houses (identified by slack_group_id). The slack_handle column is what you would use to @ a group of users in Slack (e.g. @summit).

**sl_login_tokens**: Stores hash/token pairs that are generated when users “Sign in with Slack” to access the Member Portal.

**sl_rooms**: Lookup table for physical rooms in each house (identified a combination of id and house_id). The house_id values match to slack_group_id values from sl_houses table.

**sl_users**: Lookup table for residents/boarders/users (identified by slack_user_id). The real_name column is what displays on the Work Credit Report (John Doe). The display_name column is what appears in Slack (@john) unless undefined (then it will fallback to using the real_name column as well).

**sl_view_states**: Stores values from Slack forms, so they can persist between views (Resident Services, Submit Hours, etc).

**wc_lookup_hour_types**: Lookup table for various hour types (House/Collective/Maintenance) that can be submitted (identified by id). Default debit values are stored here.

**wc_lookup_other_req_types**: Lookup table for various non-hour requirements (Dinner/Meetings) that can be submitted (identified by id).

**wc_time_credits**: Stores user-submitted hours (identified by id). The Work Credit Report totals these values (lifetime, per user, per hour type) and displays the difference between the total  against the total from wc_time_debits (less the current “in progress” debit). Additionally, tracks the source of each submission (1 - via Slack, 2 - via Member Portal website).

**wc_time_debits**: Stores (usually automatic) hour debits per user. The Work Credit Report references the most recent debit to determine what should be displayed as the “in progress” requirement (per user, per hour type).

**wc_user_req_modifiers**: Lookup table for additional hour requirements (e.g. one extra House hour for keeping a pet). The system will factor these values in before applying automated debits.