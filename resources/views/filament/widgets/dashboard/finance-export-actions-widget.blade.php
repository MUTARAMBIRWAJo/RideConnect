<div class="fi-section p-6 rounded-2xl">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Export Finance</h3>
    <p class="text-sm text-gray-500 dark:text-gray-300 mb-4">Download finance records in your preferred format.</p>

    <div class="flex flex-wrap gap-3">
        <a href="{{ url('/admin/finances') }}" class="px-4 py-2.5 rounded-lg bg-gradient-to-r from-green-800 to-green-700 text-white text-sm font-semibold shadow-sm transition-all duration-200 hover:from-green-700 hover:to-green-600 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-300 focus-visible:ring-offset-2 dark:focus-visible:ring-green-700 dark:focus-visible:ring-offset-gray-900">
            Export CSV
        </a>
        <a href="{{ url('/admin/finances') }}" class="px-4 py-2.5 rounded-lg border border-green-700 text-gray-800 dark:text-gray-200 text-sm font-semibold transition-all duration-200 hover:bg-green-50 dark:hover:bg-green-900/30 hover:border-green-600 dark:hover:border-green-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-300 focus-visible:ring-offset-2 dark:focus-visible:ring-green-700 dark:focus-visible:ring-offset-gray-900">
            Export PDF
        </a>
    </div>
</div>
