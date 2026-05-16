SETUP INSTRUCTIONS
==================

1. Database Setup:
   - Create a MySQL database named 'moneyking'.
   - Import the file 'admin/schema.sql' into this database.
   - This will create the 'users' table and a default admin account.
     - Default Admin Username: admin
     - Default Admin Password: admin123

2. Configuration:
   - Open 'admin/includes/db.php' and update the database credentials if necessary (host, user, password).

3. Access:
   - Navigate to /admin/index.php in your browser.
   - Login with the default admin credentials.

HIERARCHY & FEATURES
====================
- Hierarchy: Admin -> Master Agent -> Agent -> Player
- Coin System:
  - Admin can add/deduct coins for Master Agents.
  - Master Agents can transfer coins to/from Agents.
  - Agents can transfer coins to/from Players.
- No auto-registration. All users are created by their upline.
- UI/UX matches the provided moneyking365.com design.
