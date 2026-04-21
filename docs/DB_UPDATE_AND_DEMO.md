# Database Update And Demo Data

This project now supports a clean database update path through migrations and a single complete demo-data seeder.

## Localhost Update

From the project root:

```powershell
C:\devtools\php82\php.exe spark migrate
C:\devtools\php82\php.exe spark db:seed CompleteSystemDemoSeeder
```

## Server Update

On the server, first make sure the application `.env` has the correct database connection:

```ini
database.default.hostname = YOUR_DB_HOST
database.default.database = YOUR_DB_NAME
database.default.username = YOUR_DB_USER
database.default.password = YOUR_DB_PASSWORD
database.default.DBDriver = MySQLi
database.default.port = 3306
```

Then run:

```bash
php spark migrate
php spark db:seed CompleteSystemDemoSeeder
```

## What The Demo Seeder Loads

`CompleteSystemDemoSeeder` composes the existing demo seeders and adds:

- demo admin user
- lead and basic masters
- warehouse and bin setup
- demo customer, vendor, karigar
- order to invoice full manufacturing flow
- design and inventory sample data
- showroom, counter, showroom sale
- debit note and credit note sample data
- company settings sample row

## Demo Login

Admin:

- Email: `admin@demo.com`
- Password: `Admin@123`

Sales Executive:

- Email: `salesexec@demo.com`
- Password: `Sales@123`

## Notes

- The seeder is written to be rerunnable as much as possible and checks for existing rows before inserting key demo records.
- Migrations remain the source of truth for schema updates.
- For production-like servers, review WhatsApp API settings and company settings after seeding.
