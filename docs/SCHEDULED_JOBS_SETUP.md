# Scheduled Billing Jobs - Setup & Testing Guide

## ✅ What's Been Created

### 7 Billing Jobs
1. **ProcessMonthlyBillingJob** - Generates monthly invoices (1st of month, 00:00)
2. **RenewAgentSubscriptionsJob** - Renews agent add-ons (daily, 01:00)
3. **ResetUsageQuotasJob** - Resets execution quotas (1st of month, 02:00)
4. **SendOverdueInvoiceRemindersJob** - Sends payment reminders (daily, 10:00)
5. **RetryFailedPaymentsJob** - Retries failed payments (daily, 14:00)
6. **HandleExpiredSubscriptionsJob** - Deactivates expired subs (daily, 03:00)
7. **CleanupOldInvoicesJob** - Archives old invoices (monthly, 04:00)

### Model Methods Added
- `Subscription::resetMonthlyUsage()` - Reset monthly usage quota
- All other required methods already existed

### Scheduler Configuration
- Added to `bootstrap/app.php` with `withSchedule()`
- All jobs scheduled with Africa/Cairo timezone
- Overlap prevention enabled
- Success/failure logging configured

---

## 🚀 Setup Steps

### 1. Configure Queue

Add to `.env`:
```env
QUEUE_CONNECTION=database
```

### 2. Create Queue Tables

```bash
php artisan queue:table
php artisan queue:failed-table
php artisan migrate
```

### 3. Test Scheduler

```bash
# List all scheduled tasks
php artisan schedule:list

# Run scheduler manually (runs all due tasks)
php artisan schedule:run
```

### 4. Start Queue Worker

```bash
# Development
php artisan queue:work

# Production (with supervisor - see below)
php artisan queue:work --daemon --tries=3
```

---

## 🧪 Testing Jobs

### Test Individual Jobs

```bash
php artisan tinker
```

Then run:
```php
// Test monthly billing
App\Jobs\Billing\ProcessMonthlyBillingJob::dispatch();

// Test agent renewal
App\Jobs\Billing\RenewAgentSubscriptionsJob::dispatch();

// Test usage reset
App\Jobs\Billing\ResetUsageQuotasJob::dispatch();

// Test overdue reminders
App\Jobs\Billing\SendOverdueInvoiceRemindersJob::dispatch();

// Test failed payment retries
App\Jobs\Billing\RetryFailedPaymentsJob::dispatch();

// Test expired subscriptions
App\Jobs\Billing\HandleExpiredSubscriptionsJob::dispatch();

// Test cleanup
App\Jobs\Billing\CleanupOldInvoicesJob::dispatch();

exit
```

### Check Logs

```bash
tail -f storage/logs/laravel.log
```

Look for:
- "Starting monthly billing process"
- "Monthly billing completed"
- "Agent subscription renewed"
- etc.

---

## 📊 Production Setup

### 1. Supervisor Configuration

Create `/etc/supervisor/conf.d/obsolio-worker.conf`:

```ini
[program:obsolio-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/obsolio/htdocs/api.obsolio.com/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/home/obsolio/htdocs/api.obsolio.com/storage/logs/worker.log
stopwaitsecs=3600
```

Start supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start obsolio-worker:*
```

### 2. Cron Setup

Add to crontab:
```bash
crontab -e
```

Add this line:
```bash
* * * * * cd /home/obsolio/htdocs/api.obsolio.com && php artisan schedule:run >> /dev/null 2>&1
```

### 3. Monitor Queue

```bash
# Check queue status
php artisan queue:monitor

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

---

## 📅 Job Schedule Summary

| Job | Schedule | Time (Cairo) |
|-----|----------|--------------|
| Monthly Billing | 1st of month | 00:00 |
| Agent Renewal | Daily | 01:00 |
| Usage Reset | 1st of month | 02:00 |
| Expired Subs | Daily | 03:00 |
| Cleanup | 1st of month | 04:00 |
| Overdue Reminders | Daily | 10:00 |
| Failed Payments | Daily | 14:00 |

---

## 🔍 Verification Checklist

- [ ] Queue tables created
- [ ] Queue worker running
- [ ] Cron configured
- [ ] Supervisor configured (production)
- [ ] Test jobs manually
- [ ] Check logs for errors
- [ ] Verify scheduler with `schedule:list`
- [ ] Test end-to-end billing cycle

---

## 🎯 Next Steps

1. **Configure Queue** - Run queue migrations
2. **Test Jobs** - Test each job individually
3. **Setup Cron** - Add scheduler to crontab
4. **Setup Supervisor** - Configure queue workers
5. **Monitor** - Watch logs for first billing cycle

---

## 📝 Notes

- All jobs use `withoutOverlapping()` to prevent concurrent runs
- Jobs are logged to `storage/logs/laravel.log`
- Failed jobs are stored in `failed_jobs` table
- Timezone is set to `Africa/Cairo` for all jobs

---

**Status**: ✅ All jobs created and scheduled. Ready for queue configuration and testing!
