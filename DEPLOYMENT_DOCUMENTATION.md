# 📚 Obsolio Platform - توثيق شامل للمشروع

## 📋 فهرس المحتويات

1. [نظرة عامة](#نظرة-عامة)
2. [البنية التحتية](#البنية-التحتية)
3. [Backend API](#backend-api)
4. [Frontend Application](#frontend-application)
5. [قاعدة البيانات](#قاعدة-البيانات)
6. [المصادقة والأمان](#المصادقة-والأمان)
7. [API Endpoints](#api-endpoints)
8. [الصيانة والتحديث](#الصيانة-والتحديث)
9. [استكشاف الأخطاء](#استكشاف-الأخطاء)

---

## 🌐 نظرة عامة

**Obsolio AI Platform** هي منصة ذكاء اصطناعي متقدمة لإدارة وتنفيذ AI Agents مع دعم:

- �� **AI Agent Management** - إنشاء وإدارة وتنفيذ الوكلاء الذكية
- 🔄 **Workflow Orchestration** - بناء وتنفيذ سير عمل معقدة
- 👥 **Multi-Tenancy** - عزل كامل بين المستأجرين
- 🔐 **RBAC** - التحكم الكامل بالصلاحيات
- 📊 **Analytics** - تحليلات شاملة للأداء

### الروابط الرئيسية

| الخدمة | الرابط |
|--------|--------|
| **Frontend** | https://obsolio.com |
| **Backend API** | https://api.obsolio.com |
| **API Documentation** | https://api.obsolio.com/api/documentation |
| **Health Check** | https://api.obsolio.com/api/health |

---

## 🏗️ البنية التحتية

### معلومات السيرفر

| المكون | التفاصيل |
|--------|----------|
| **السيرفر** | 72.62.59.158 |
| **نظام التشغيل** | Ubuntu (Debian) |
| **لوحة التحكم** | CloudPanel |
| **Web Server** | Nginx |
| **PHP Version** | 8.3 |
| **Node.js** | 18.x |
| **Database** | PostgreSQL 16 |

### هيكل الملفات

```
/home/
├── obsolio/                          # Backend User
│   ├── htdocs/
│   │   └── api.obsolio.com/          # Laravel Backend
│   │       ├── app/
│   │       ├── config/
│   │       ├── database/
│   │       ├── public/               # Nginx root
│   │       ├── routes/
│   │       ├── storage/
│   │       └── .env
│   └── logs/
│       ├── nginx/
│       └── php/
│
└── n8nuser/                          # Frontend User
    └── htdocs/
        └── obsolio.com/
            ├── src/                  # React source
            ├── dist/                 # Built files (Nginx root)
            └── .env
```

---

## 🔧 Backend API

### التقنيات المستخدمة

- **Framework**: Laravel 11
- **PHP**: 8.3
- **Database**: PostgreSQL 16
- **Authentication**: JWT (tymon/jwt-auth)
- **Multi-Tenancy**: stancl/tenancy
- **Permissions**: spatie/laravel-permission
- **API Docs**: L5-Swagger

### المسار
```
/home/obsolio/htdocs/api.obsolio.com/
```

### أوامر Laravel الشائعة

```bash
cd /home/obsolio/htdocs/api.obsolio.com

# تحديث الكود
git pull origin main

# تثبيت dependencies
composer install --no-dev --optimize-autoloader

# تشغيل migrations
php artisan migrate --force

# إعادة بناء cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# مسح cache
php artisan cache:clear
php artisan config:clear
```

---

## 🎨 Frontend Application

### التقنيات المستخدمة

- **Framework**: React 18
- **Build Tool**: Vite
- **Styling**: Tailwind CSS

### المسار
```
/home/n8nuser/htdocs/obsolio.com/
```

### أوامر التحديث

```bash
cd /home/n8nuser/htdocs/obsolio.com

git pull origin main
npm install
npm run build
chown -R n8nuser:n8nuser /home/n8nuser/htdocs/obsolio.com/
```

---

## 🗄️ قاعدة البيانات

| المكون | القيمة |
|--------|--------|
| **Type** | PostgreSQL 16 |
| **Host** | 127.0.0.1 |
| **Port** | 5432 |
| **Database** | obsolio_db |
| **Username** | obsolio |
| **Password** | Obs0li0DB@2025 |

### Backup
```bash
pg_dump -U obsolio -h 127.0.0.1 obsolio_db > backup_$(date +%Y%m%d).sql
```

---

## 🔐 المصادقة (JWT)

```bash
# تسجيل الدخول
POST /api/v1/auth/login
{
  "email": "user@example.com",
  "password": "password123"
}

# استخدام Token
Authorization: Bearer <access_token>
```

---

## 📡 API Endpoints الرئيسية

### Health
- GET /api/health
- GET /api/health/ready

### Authentication
- POST /api/v1/auth/register
- POST /api/v1/auth/login
- POST /api/v1/auth/logout
- GET /api/v1/auth/me

### Agents
- GET/POST /api/v1/agents
- GET/PUT/DELETE /api/v1/agents/{id}
- POST /api/v1/agents/{id}/execute

### Workflows
- GET/POST /api/v1/workflows
- GET/PUT/DELETE /api/v1/workflows/{id}
- POST /api/v1/workflows/{id}/execute

### Organizations
- GET/POST /api/v1/organizations
- GET/PUT/DELETE /api/v1/organizations/{id}

### Users
- GET/POST /api/v1/users
- GET/PUT/DELETE /api/v1/users/{id}

---

## 🔄 الصيانة

### تحديث Backend
```bash
cd /home/obsolio/htdocs/api.obsolio.com
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache
chown -R obsolio:obsolio /home/obsolio/htdocs/api.obsolio.com
systemctl restart php8.3-fpm
```

### تحديث Frontend
```bash
cd /home/n8nuser/htdocs/obsolio.com
git pull origin main
npm install && npm run build
chown -R n8nuser:n8nuser /home/n8nuser/htdocs/obsolio.com/
```

### تجديد SSL
```bash
clpctl lets-encrypt:install:certificate --domainName=obsolio.com --subjectAlternativeName=www.obsolio.com
clpctl lets-encrypt:install:certificate --domainName=api.obsolio.com
```

---

## 🔧 استكشاف الأخطاء

```bash
# فحص الخدمات
systemctl status nginx
systemctl status php8.3-fpm
systemctl status postgresql

# عرض Logs
tail -f /home/obsolio/logs/nginx/error.log
tail -f /home/obsolio/logs/php/error.log

# مشاكل Permission
chown -R obsolio:obsolio /home/obsolio/htdocs/api.obsolio.com
chmod -R 755 /home/obsolio/htdocs/api.obsolio.com/storage

# مسح Cache
php artisan cache:clear
php artisan config:clear
```

---

## 📊 ملخص

```
┌─────────────────────────────────────────────────────────────┐
│                    Obsolio AI Platform                       │
├─────────────────────────────────────────────────────────────┤
│   Frontend (React)  ───►  Backend (Laravel)  ───►  PostgreSQL│
│   obsolio.com            api.obsolio.com        obsolio_db   │
├─────────────────────────────────────────────────────────────┤
│  Server: 72.62.59.158 | Nginx | PHP 8.3 | Node 18           │
└─────────────────────────────────────────────────────────────┘
```

---

**آخر تحديث**: December 9, 2025
