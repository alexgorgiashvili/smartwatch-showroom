# Server & Deploy Shortcuts (PowerShell)

ეს არის შენთვის მოკლე სწრაფი გაიდი, რომ ბრძანებები არ დაგავიწყდეს.

## 1) One-time setup

- Save default server:
  - `sset "mytechn1@142.132.203.78"`
- Reload profile in current terminal:
  - `. $PROFILE`

## 2) ყოველდღიური სწრაფი ბრძანებები

- SSH connect (default server):
  - `s`
- Run one remote command:
  - `sr "whoami; pwd"`
- Open SSH პირდაპირ project folder-ში:
  - `sproj`
- Deploy script run on server:
  - `sd`
- Local git push shortcut (`add + commit + push`):
  - `gpush "commit message"`
- Full deploy + smoke-check (ერთ ბრძანებაში):
  - `sfull`
- Full DB sync local -> server (backup + import + migrate):
  - `sdbsync`

## 3) სასარგებლო დამატებები

- Show saved default server:
  - `sget`
- If shortcut არ მუშაობს ახალ ტერმინალში:
  - `. $PROFILE`

## 4) Current configured values

- Server: `mytechn1@142.132.203.78`
- Project path: `/home/mytechn1/public_html/smartwatch-showroom`
- Domain (for `sfull` smoke-check): `https://mytechnic.ge`

## 5) Quick flow

- კოდის ატვირთვა GitHub-ზე:
  - `gpush "your message"`
- production deploy + smoke-check:
  - `sfull`

## 6) DB quick flow (local -> server)

- Run full DB sync in one command:
  - `sdbsync`

რას აკეთებს `sdbsync` ავტომატურად:
- ქმნის local dump-ს (`--result-file`)
- სერვერზე აკეთებს backup-ს import-მდე
- ტვირთავს dump-ს სერვერზე
- აკეთებს import-ს `mariadb --binary-mode=1`-ით
- უშვებს `artisan migrate --force`

თუ defaults-ის შეცვლა გინდა (მაგალითად სხვა DB):
- `sdbsync -LocalDb "my_local_db" -RemoteDb "my_remote_db"`
