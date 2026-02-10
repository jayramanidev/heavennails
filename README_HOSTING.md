# Hosting Guide for Heaven Nails (InfinityFree)

This guide will help you host your website on InfinityFree.

## Prerequisites
- An InfinityFree account (or any PHP hosting).
- A domain name (or use a free subdomain from InfinityFree).

## Step 1: File Preparation (Already Done)
We have already prepared your files:
1.  **Configuration**: `php/secrets.php` is ready for production credentials.
2.  **Database**: `database/database.sql` is ready for import.
3.  **Routing**: `.htaccess` is updated to work on the root domain.

## Step 2: Database Setup
1.  Log in to your **InfinityFree Client Area** and go to the **Control Panel** (VistaPanel).
2.  Click on **"MySQL Databases"**.
3.  Create a new database (e.g., `heaven_nails`). Note down the **Database Name**, **MySQL Username**, **MySQL Password** (found in Client Area), and **MySQL Hostname** (usually something like `sql123.infinityfree.com`).
4.  Go back to the Control Panel and click **"phpMyAdmin"**.
5.  Click "Connect Now" next to your new database.
6.  In phpMyAdmin, click the **"Import"** tab at the top.
7.  Click **"Choose File"** and select `database/database.sql` from your project folder.
8.  Click **"Go"** at the bottom. You should see a success message.

## Step 3: Upload Files
1.  Open the **"Online File Manager"** from the Control Panel (or use an FTP client like FileZilla).
2.  Navigate to the `htdocs` folder. This is your public folder.
3.  **Delete** the default `index2.html` or `default.php` if they exist.
4.  **Upload** all files and folders from your `HEAVEN` project folder directly into `htdocs`.
    - `assets/`
    - `css/`
    - `database/`
    - `js/`
    - `php/`
    - `index.html`
    - `index.php`
    - `.htaccess`
    - `robots.txt`
    - `sitemap.xml`
    
    *Note: Do NOT upload the `.git` folder or `.gitignore`.*

## Step 4: Final Configuration
1.  In the File Manager, navigate to `php/secrets.php`.
2.  **Edit** the file.
3.  Find the **PRODUCTION** section.
4.  Uncomment the define statements and replace the placeholders with your actual database credentials from Step 2.
    ```php
    // PRODUCTION (InfinityFree / Others)
    define('DB_HOST', 'sqlxxx.infinityfree.com');
    define('DB_NAME', 'if0_xxxxxxx_heaven_nails');
    define('DB_USER', 'if0_xxxxxxx');
    define('DB_PASS', 'YourRealPassword');
    ```
5.  Save the file.

## Step 5: Test
1.  Visit your website URL.
2.  Test the booking form to make sure it saves to the database.
3.  Try logging into `/admin` (Default: `admin` / `heaven2026`).

## Troubleshooting
- **404 Errors**: Check if `.htaccess` was uploaded correctly.
- **Database Connection Error**: Double-check credentials in `php/secrets.php`. Ensure you aren't using `localhost` for the host if InfinityFree gave you a specific SQL host.
- **Styling Issues**: Clear your browser cache (Ctrl+F5).
