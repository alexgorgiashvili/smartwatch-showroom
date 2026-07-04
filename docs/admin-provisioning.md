# Admin provisioning

Create the first production administrator interactively:

```bash
php artisan admin:create
```

The command asks for the name, email address, and password. Password input is
hidden and is never stored in source control. It must contain at least 12
characters, mixed-case letters, a number, and a symbol.

Do not create production administrators from `DatabaseSeeder`, and do not
commit database dump files. Root-level `dump_*.sql` files are intentionally
ignored because local database synchronization creates them as temporary
transfer artifacts.
