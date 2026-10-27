<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\GatewayRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SystemController extends Controller
{
    public function audit(Request $request): View
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return view('admin.system.audit', [
            'logs' => AuditLog::query()->with('user')
                ->when($request->filled('action'), fn ($q) => $q->where('action', 'ilike', $request->string('action').'%'))
                ->when($request->filled('user'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('email', 'ilike', '%'.$request->string('user').'%')))
                ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
                ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
                ->latest('created_at')->paginate(50)->withQueryString(),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    public function health(GatewayRegistry $gateways): View
    {
        $checks = [];

        try {
            $version = DB::selectOne('select version() as v')->v;
            $checks[] = ['label' => __('Database'), 'ok' => true, 'detail' => Str::limit($version, 60)];
        } catch (Throwable $e) {
            $checks[] = ['label' => __('Database'), 'ok' => false, 'detail' => $e->getMessage()];
        }

        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $checks[] = ['label' => __('Failed queue jobs'), 'ok' => $failedJobs === 0, 'detail' => trans_choice(':count failed job|:count failed jobs', $failedJobs, ['count' => $failedJobs]).($failedJobs ? ' — '.__('run php artisan queue:retry all after fixing the cause') : '')];

        $queued = config('queue.default') === 'database' && Schema::hasTable('jobs') ? DB::table('jobs')->count() : null;
        $oldest = $queued ? DB::table('jobs')->min('created_at') : null;
        $checks[] = ['label' => __('Queue (:driver)', ['driver' => config('queue.default')]), 'ok' => $oldest === null || now()->timestamp - $oldest < 600, 'detail' => $queued === null ? __('Not a database queue') : __(':n waiting', ['n' => $queued]).($oldest ? ' · '.__('oldest :age', ['age' => now()->createFromTimestamp($oldest)->diffForHumans()]) : '')];

        $stale = Payment::query()->where('status', PaymentStatus::Pending)->where('created_at', '<', now()->subMinutes(30))->count();
        $checks[] = ['label' => __('Payments pending > 30 min'), 'ok' => $stale === 0, 'detail' => $stale ? __(':n need reconciliation (scheduler runs payments:reconcile)', ['n' => $stale]) : __('None')];

        foreach (array_unique([config('marketplace.artwork_disk'), 'public']) as $disk) {
            try {
                $probe = 'health/'.uniqid().'.txt';
                Storage::disk($disk)->put($probe, 'ok');
                Storage::disk($disk)->delete($probe);
                $checks[] = ['label' => __('Storage disk “:d”', ['d' => $disk]), 'ok' => true, 'detail' => __('Writable')];
            } catch (Throwable $e) {
                $checks[] = ['label' => __('Storage disk “:d”', ['d' => $disk]), 'ok' => false, 'detail' => $e->getMessage()];
            }
        }

        $checks[] = ['label' => __('Debug mode'), 'ok' => ! (app()->isProduction() && config('app.debug')), 'detail' => config('app.debug') ? __('On (must be off in production)') : __('Off')];
        $checks[] = ['label' => __('Environment'), 'ok' => true, 'detail' => app()->environment().' · PHP '.PHP_VERSION.' · Laravel '.app()->version()];
        $checks[] = ['label' => __('Mail driver'), 'ok' => ! app()->isProduction() || ! in_array(config('mail.default'), ['log', 'array'], true), 'detail' => config('mail.default')];
        $checks[] = ['label' => __('Super administrators'), 'ok' => true, 'detail' => (string) User::query()->whereHas('roles', fn ($q) => $q->where('slug', 'super_admin'))->count()];

        $providers = collect(config('payments.checkout_order'))->map(function ($key) use ($gateways) {
            $gateway = $gateways->get($key);

            return ['key' => $key, 'label' => $gateway->label(), 'configured' => $gateway->isConfigured(), 'live' => $gateway->isLive(), 'enabled' => (bool) config("payments.providers.{$key}.enabled")];
        });

        return view('admin.system.health', ['checks' => $checks, 'providers' => $providers]);
    }
}
