@php
    $currentRoute = request()->routeIs('*') ? Route::currentRouteName() : '';
@endphp

<!-- Dashboard — all roles -->
<a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : (auth()->user()->isDoctor() ? route('doctor.dashboard') : route('dashboard')) }}"
   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('dashboard') || request()->routeIs('doctor.dashboard') || request()->routeIs('admin.dashboard') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
    </svg>
    Dashboard
</a>

@if(auth()->user()->isAdmin())
    <!-- Admin Section -->
    <div class="mt-6 mb-2 px-3">
        <p class="text-xs font-semibold text-zapmed-400 uppercase tracking-wider">Administration</p>
    </div>

    <a href="{{ route('admin.dashboard') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.dashboard') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
        Overview
    </a>

    <a href="{{ route('admin.users') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.users') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        Users
    </a>

    <a href="{{ route('admin.appointments') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.appointments') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        Appointments
    </a>

    <a href="{{ route('admin.payments') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.payments') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Payments
    </a>

    <a href="{{ route('admin.failed-payments') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.failed-payments') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        Failed Payments
        @php $failedPaymentCount = \App\Models\Subscription::where('status', 'payment_failed')->count(); @endphp
        @if($failedPaymentCount > 0)
            <span class="ml-auto bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">{{ $failedPaymentCount }}</span>
        @endif
    </a>

    <a href="{{ route('admin.order-board') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.order-board') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
        </svg>
        Order Board
    </a>

    <a href="{{ route('admin.leads-funnel') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.leads-funnel') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.879a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
        </svg>
        Leads &amp; Funnel
    </a>

    <a href="{{ route('admin.patient-360') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.patient-360') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
        Patient 360
    </a>

    <a href="{{ route('admin.alerts') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.alerts') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        Alerts
        @php $openCriticalCount = \App\Models\Alert::whereIn('status', ['open','acknowledged','snoozed'])->where('severity','critical')->count(); @endphp
        @if($openCriticalCount > 0)
            <span class="ml-auto bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">{{ $openCriticalCount }}</span>
        @endif
    </a>

    <a href="{{ route('admin.coach-console') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.coach-console') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a3 3 0 10-2.83-4" />
        </svg>
        Coach Console
    </a>

    <a href="{{ route('admin.contro-import') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.contro-import') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
        </svg>
        Contro Import
        @php $quarantineCount = \App\Models\ImportQuarantine::where('status', 'open')->count(); @endphp
        @if($quarantineCount > 0)
            <span class="ml-auto bg-amber-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">{{ $quarantineCount }}</span>
        @endif
    </a>

    <a href="{{ route('admin.crm-analytics') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.crm-analytics') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
        </svg>
        CRM Analytics
    </a>

    <a href="{{ route('admin.finance') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.finance') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m-6 4h6m-6 4h4M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
        </svg>
        Finance
    </a>

    <a href="{{ route('admin.ai-nudges') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.ai-nudges') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
        AI Nudges
        @php $pendingNudges = \App\Models\CrmNudge::whereIn('status', ['draft','approved'])->count(); @endphp
        @if($pendingNudges > 0)
            <span class="ml-auto bg-indigo-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">{{ $pendingNudges }}</span>
        @endif
    </a>

    <a href="{{ route('admin.compliance') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.compliance') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        Compliance
        @php $overdueDsars = \App\Models\ComplianceDsar::whereIn('status', ['received','acknowledged','in_progress'])->where('due_at','<',now())->count(); @endphp
        @if($overdueDsars > 0)
            <span class="ml-auto bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">{{ $overdueDsars }}</span>
        @endif
    </a>

    <a href="{{ route('admin.subscriptions') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.subscriptions') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        Subscriptions
    </a>

    <a href="{{ route('admin.medications') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.medications') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
        </svg>
        Medications
    </a>

    <a href="{{ route('admin.ai') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.ai') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
        </svg>
        AI Assistant
    </a>

    <a href="{{ route('admin.analytics') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.analytics') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
        Analytics
    </a>

    <a href="{{ route('admin.partners') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.partners') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9" />
        </svg>
        Partners
    </a>

    <a href="{{ route('admin.newsletters') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.newsletters') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
        Newsletters
    </a>

    <a href="{{ route('admin.blog') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.blog') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
        </svg>
        Blog
    </a>

    <a href="{{ route('admin.help-center') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.help-center') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
        </svg>
        Help Center
    </a>

    <a href="{{ route('admin.audit-log') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('admin.audit-log') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        Audit Log
    </a>

    <a href="{{ route('admin.doctor-applications') }}"
       class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.doctor-applications') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
        </svg>
        Doctor Applications
        @php $pendingCount = \App\Models\DoctorApplication::where('status', 'pending')->count(); @endphp
        @if($pendingCount > 0)
            <span class="ml-auto bg-yellow-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">{{ $pendingCount }}</span>
        @endif
    </a>

    <!-- SPAR Section -->
    <div class="mt-4 pt-4 border-t border-slate-700/50" x-data="{ sparOpen: {{ request()->routeIs('admin.spar.*') ? 'true' : 'false' }} }">
        <button @click="sparOpen = !sparOpen" class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.spar.*') ? 'bg-green-600/20 text-green-300' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                SPAR Medication
            </div>
            <svg class="w-4 h-4 transition-transform duration-200" :class="sparOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="sparOpen" x-collapse class="mt-1 ml-4 pl-4 border-l border-slate-700/50 space-y-1">
            <a href="{{ route('admin.spar.dashboard') }}" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.spar.dashboard') ? 'text-green-300 bg-green-600/10' : 'text-slate-400 hover:text-white hover:bg-sidebar-hover' }}">
                <svg class="w-4 h-4 mr-2.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                Overview
            </a>
            <a href="{{ route('admin.spar.pharmacies') }}" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.spar.pharmacies') ? 'text-green-300 bg-green-600/10' : 'text-slate-400 hover:text-white hover:bg-sidebar-hover' }}">
                <svg class="w-4 h-4 mr-2.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
                Pharmacies
            </a>
            <a href="{{ route('admin.spar.imports') }}" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.spar.imports') ? 'text-green-300 bg-green-600/10' : 'text-slate-400 hover:text-white hover:bg-sidebar-hover' }}">
                <svg class="w-4 h-4 mr-2.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                Data Imports
            </a>
            <a href="{{ route('admin.spar.exceptions') }}" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.spar.exceptions') ? 'text-green-300 bg-green-600/10' : 'text-slate-400 hover:text-white hover:bg-sidebar-hover' }}">
                <svg class="w-4 h-4 mr-2.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Exceptions
            </a>
            <a href="{{ route('admin.spar.reporting') }}" class="flex items-center px-3 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('admin.spar.reporting') ? 'text-green-300 bg-green-600/10' : 'text-slate-400 hover:text-white hover:bg-sidebar-hover' }}">
                <svg class="w-4 h-4 mr-2.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Reporting
            </a>
        </div>
    </div>
@endif

@if(auth()->user()->isDoctor())
    <!-- Doctor Section -->
    <div class="mt-6 mb-2 px-3">
        <p class="text-xs font-semibold text-zapmed-400 uppercase tracking-wider">Consultations</p>
    </div>

    <a href="{{ route('doctor.dashboard') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('doctor.dashboard') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        Appointments
    </a>

    <a href="{{ route('doctor.patients') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('doctor.patients') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        My Patients
    </a>

    <a href="{{ route('doctor.prescriptions') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('doctor.prescriptions') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
        </svg>
        Prescriptions
    </a>

    <a href="{{ route('doctor.availability') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('doctor.availability') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Availability
    </a>

    <div class="mt-6 mb-2 px-3">
        <p class="text-xs font-semibold text-zapmed-400 uppercase tracking-wider">Communication</p>
    </div>

    <a href="{{ route('doctor.messages') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('doctor.messages') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
        Messages
    </a>
@endif

@if(auth()->user()->isPatient())
    <!-- Patient Section -->
    <div class="mt-6 mb-2 px-3">
        <p class="text-xs font-semibold text-zapmed-400 uppercase tracking-wider">My Health</p>
    </div>

    <a href="{{ route('patient.book') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('patient.book') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        Book Appointment
    </a>

    <a href="{{ route('patient.appointments') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('patient.appointments') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
        My Appointments
    </a>

    <a href="{{ route('patient.subscription') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('patient.subscription') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        My Subscription
    </a>

    <a href="{{ route('patient.prescriptions') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors border-b border-slate-700/50 {{ request()->routeIs('patient.prescriptions') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
        </svg>
        Prescriptions
    </a>

    <a href="{{ route('patient.progress') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('patient.progress') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        My Progress
    </a>

    <div class="mt-6 mb-2 px-3">
        <p class="text-xs font-semibold text-zapmed-400 uppercase tracking-wider">Communication</p>
    </div>

    <a href="{{ route('dashboard') }}" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg text-slate-300 hover:bg-sidebar-hover hover:text-white transition-colors">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
        Messages
    </a>
@endif

<!-- Profile — all roles -->
<div class="mt-6 mb-2 px-3">
    <p class="text-xs font-semibold text-zapmed-400 uppercase tracking-wider">Account</p>
</div>

<a href="{{ route('profile') }}"
   class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('profile') ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white' }}">
    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
    </svg>
    Profile
</a>
