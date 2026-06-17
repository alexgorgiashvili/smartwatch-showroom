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
- Server git push shortcut (სერვერიდან GitHub-ზე):
  - `spush "commit message"`
- Pull latest code from GitHub to local:
  - `spull`
- Pull latest code from GitHub to server:
  - `spullserver`
- Full deploy + smoke-check (ერთ ბრძანებაში):
  - `sfull`
- Full DB sync local -> server (backup + import + migrate):
  - `sdbsync`
- Meta local server + tunnel start:
  - `smeta`
- Meta local server + tunnel stop:
  - `smetaoff`
- Meta local server + tunnel logs:
  - `smetalog`

## 3) სასარგებლო დამატებები

- Show saved default server:
  - `sget`
- If shortcut არ მუშაობს ახალ ტერმინალში:
  - `. $PROFILE`
- If `smeta` ახალი დამატებულია და ჯერ არ ჩანს:
  - `. $PROFILE`

## 4) Current configured values

- Server: `mytechn1@142.132.203.78`
- Project path: `/home/mytechn1/public_html/smartwatch-showroom`
- Domain (for `sfull` smoke-check): `https://mytechnic.ge`

## 5) Quick flow

- კოდის ატვირთვა ლოკალიდან GitHub-ზე:
  - `gpush "your message"`
- კოდის ატვირთვა სერვერიდან GitHub-ზე:
  - `spush "your message"`
- GitHub-დან ლოკალზე pull:
  - `spull`
- GitHub-დან სერვერზე pull:
  - `spullserver`
- production deploy + smoke-check:
  - `sfull`
- Meta local webhook test start:
  - `smeta`
- Meta local webhook test stop:
  - `smetaoff`
- Meta callback URL after `smeta`:
  - `https://kidsim.loca.lt/api/webhooks/messages`

## 6) DB quick flow (local -> server)

- Run full DB sync in one command:
  - `sdbsync`
- Run safe catalog-only sync in one command:
  - `sdbsyncsafe`

რას აკეთებს `sdbsync` ავტომატურად:
- ქმნის local dump-ს (`--result-file`)
- სერვერზე აკეთებს backup-ს import-მდე
- ტვირთავს dump-ს სერვერზე
- აკეთებს import-ს `mariadb --binary-mode=1`-ით
- უშვებს `artisan migrate --force`

თუ defaults-ის შეცვლა გინდა (მაგალითად სხვა DB):
- `sdbsync -LocalDb "my_local_db" -RemoteDb "my_remote_db"`

## 7) Meta local tunnel

- Start Laravel + localtunnel in current VS Code terminal session:
  - `smeta`
- Stop background jobs:
  - `smetaoff`
- Read job output:
  - `smetalog`
- Different subdomain if needed:
  - `smeta -Subdomain "anothername"`
- Different port if needed:
  - `smeta -Port 8001`

რას აკეთებს `smeta`:
- ამოწმებს `8000` port-ზე Laravel უკვე უსმენს თუ არა
- თუ არა, ამავე PowerShell session-ში რთავს background job-ს `php artisan serve`-ისთვის
- ამავე PowerShell session-ში რთავს background job-ს `lt --port 8000 --subdomain kidsim`-ისთვის
- გიჩვენებს Meta callback URL-ს: `https://kidsim.loca.lt/api/webhooks/messages`

## 8) Git shortcuts (push & pull)

### spush - Push from server to GitHub
- სერვერიდან GitHub-ზე კოდის ატვირთვა (როგორც `gpush` ლოკალზე):
  - `spush "commit message"`
  - `spush "fix bug" -Branch "develop"` (სხვა branch-ისთვის)

რას აკეთებს `spush`:
1. სერვერზე აკონფიგურებს git user-ს (თუ არ არის)
2. აკეთებს `git add .`
3. აკეთებს `git commit -m "your message"`
4. აკეთებს `git push origin main`

**გამოყენება:**
```powershell
spush "server hotfix applied"
```

### spull - Pull from GitHub to local
- GitHub-დან ლოკალზე კოდის გადმოტანა:
  - `spull`
  - `spull -Branch "develop"` (სხვა branch-ისთვის)

რას აკეთებს `spull`:
- აკეთებს `git pull origin main` ლოკალურად
- მარტივი და სწრაფი გზა GitHub-დან ბოლო ცვლილებების მისაღებად

### spullserver - Pull from GitHub to server
- GitHub-დან სერვერზე კოდის გადმოტანა:
  - `spullserver`
  - `spullserver -Branch "develop"` (სხვა branch-ისთვის)

რას აკეთებს `spullserver`:
- სერვერზე აკეთებს `git stash` (დროებით ინახავს ცვლილებებს)
- აკეთებს `git pull origin main`
- აბრუნებს stash-ს თუ იყო ცვლილებები

---

### 🔄 სრული workflow მაგალითი:

**სცენარი 1: სერვერზე ცვლილება → ლოკალზე გადმოტანა**
```powershell
# სერვერზე რამე შეცვალე და გინდა ლოკალზე გადმოიტანო
spush "server changes"           # სერვერიდან GitHub-ზე
spull                             # GitHub-დან ლოკალზე
```

**სცენარი 2: ლოკალზე ცვლილება → სერვერზე გადატანა**
```powershell
gpush "local changes"             # ლოკალიდან GitHub-ზე
spullserver                       # GitHub-დან სერვერზე
# ან
sfull                             # full deploy + smoke-check
```
