# Safe Deploy Shortcuts

ეს shortcut-ები აკეთებს მხოლოდ უსაფრთხო Git sync-ს და არ უშვებს მძიმე production ბრძანებებს.

default server values:
- Host: `142.132.203.78`
- User: `mytechn1`
- Path: `/home/mytechn1/public_html/smartwatch-showroom`

არ აკეთებს:
- `git add .`
- auto commit
- `composer install`
- `npm run build`
- `php artisan migrate --force`
- cache clear / cache rebuild
- queue restart

## 1. One-command safe sync

სურვილის შემთხვევაში შეგიძლია override გაუკეთო environment variables-ით:

```powershell
$env:DEPLOY_SSH_HOST = "your-server-host"
$env:DEPLOY_SSH_USER = "your-ssh-user"
$env:DEPLOY_SSH_PATH = "/absolute/path/to/project"
$env:DEPLOY_SSH_PORT = "22"
```

შემდეგ გაუშვი:

```powershell
.\safe-sync.ps1
```

ეს script:
- ამოწმებს რომ local working tree სუფთაა
- ამოწმებს რომ სწორ branch-ზე ხარ
- აკეთებს მხოლოდ `git push origin main`
- შემდეგ სერვერზე აკეთებს მხოლოდ `git pull --ff-only origin main`

თუ გაქვს uncommitted ცვლილებები, გაჩერდება.

## 2. One-command commit + push + server pull

თუ გინდა `gdeploy "message"` ტიპის flow:

```powershell
.\gdeploy.ps1 "update chatbot prompts"
```

ეს script:
- აკეთებს `git add -A`
- ქმნის commit-ს მითითებული message-ით
- უშვებს `safe-sync.ps1`

მნიშვნელოვანი შენიშვნა:
- ეს ავტომატურად ამატებს ყველა მიმდინარე ცვლილებას
- სანამ გაუშვებ, კარგი იდეაა ერთხელ ნახო `git status`

თუ გინდა უფრო მოკლე launcher:

```powershell
.\gsafedeploy.cmd "update chatbot prompts"
```

თუ `PATH` სწორადაა დაყენებული, უბრალოდ ასე:

```powershell
gsafedeploy "update chatbot prompts"
```

## 3. Separate commands თუ დაგჭირდება

ლოკალურად მხოლოდ push:

```powershell
.\safe-push.ps1
```

სერვერზე მხოლოდ pull:

```powershell
.\safe-server-pull.ps1
```

## შენიშვნა

ეს არის safe Git sync shortcut და იდეალურად გამოდგება იმ ცვლილებებისთვის, რომლებიც მხოლოდ კოდის განახლებას ითხოვს.

თუ production-ზე ახალ commit-ს სჭირდება:
- build
- composer install/update
- migrations
- cache refresh
- queue restart

ეს ნაბიჯები ცალკე, გააზრებულად უნდა გაუშვა.
