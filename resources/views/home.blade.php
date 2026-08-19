<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Homepage</title>
    <!-- Tailwind CSS & Alpine.js CDN for quick setup -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 font-sans antialiased">

    <!-- App Container with Alpine.js state for Sidebar Toggle -->
    <div x-data="{ sidebarOpen: true, profileDropdown: false }" class="min-h-screen flex flex-col">

        <!-- Top Navigation Bar -->
        <header class="bg-white shadow-sm border-b border-gray-200 z-10 flex justify-between items-center h-16 px-4">
            
            <!-- Left: Sidebar Toggle Button -->
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-md hover:bg-gray-100 focus:outline-none text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <span class="font-bold text-lg text-gray-800">My App</span>
            </div>

            <!-- Right: Profile Icon & Logout Dropdown -->
            <div class="relative">
                <button @click="profileDropdown = !profileDropdown" class="flex items-center gap-2 p-2 rounded-full hover:bg-gray-100 focus:outline-none">
                    <div class="w-9 h-9 rounded-full bg-blue-600 text-white flex items-center justify-center font-semibold text-sm">
                        {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                    </div>
                    <span class="text-sm font-medium text-gray-700 hidden sm:inline">{{ auth()->user()->name ?? 'User Name' }}</span>
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <!-- Profile Dropdown Menu -->
                <div x-show="profileDropdown" 
                     @click.outside="profileDropdown = false" 
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 border border-gray-100 z-20" 
                     style="display: none;">
                    
                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile Settings</a>
                    <hr class="my-1 border-gray-100">
                    
                    <!-- Logout Form -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                            Log Out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <!-- Main Wrapper (Sidebar + Main Content Area) -->
        <div class="flex flex-1 overflow-hidden">

            <!-- Collapsible Side Nav -->
            <aside :class="sidebarOpen ? 'w-64' : 'w-20'" 
                   class="bg-gray-900 text-gray-300 transition-all duration-300 ease-in-out flex flex-col">
                
                <nav class="mt-4 flex-1 px-3 space-y-1">
                    <!-- Dashboard Option -->
                    <a href="#" class="flex items-center px-3 py-3 rounded-lg hover:bg-gray-800 text-white bg-gray-800 group">
                        <svg class="w-6 h-6 flex-shrink-0 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        <span x-show="sidebarOpen" class="ml-3 font-medium transition-opacity duration-200">Dashboard</span>
                    </a>

                    <!-- Option 1 -->
                    <a href="#" class="flex items-center px-3 py-3 rounded-lg hover:bg-gray-800 group">
                        <svg class="w-6 h-6 flex-shrink-0 text-gray-400 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span x-show="sidebarOpen" class="ml-3 font-medium transition-opacity duration-200">Option 1</span>
                    </a>

                    <!-- Option 2 -->
                    <a href="#" class="flex items-center px-3 py-3 rounded-lg hover:bg-gray-800 group">
                        <svg class="w-6 h-6 flex-shrink-0 text-gray-400 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        <span x-show="sidebarOpen" class="ml-3 font-medium transition-opacity duration-200">Option 2</span>
                    </a>

                    <!-- Option 3 -->
                    <a href="#" class="flex items-center px-3 py-3 rounded-lg hover:bg-gray-800 group">
                        <svg class="w-6 h-6 flex-shrink-0 text-gray-400 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        </svg>
                        <span x-show="sidebarOpen" class="ml-3 font-medium transition-opacity duration-200">Option 3</span>
                    </a>
                </nav>
            </aside>

            <!-- Main Dashboard Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <h1 class="text-2xl font-bold text-gray-800 mb-6">Dashboard Overview</h1>

                <!-- Metric Cards (Squares) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    
                    <!-- Card 1: Pending RFAs -->
                    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Pending RFAs</p>
                            <h2 class="text-3xl font-extrabold text-gray-900 mt-1">{{ $pendingRfasCount ?? 42 }}</h2>
                        </div>
                        <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Card 2: Beyond Pct -->
                    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Beyond PCT</p>
                            <h2 class="text-3xl font-extrabold text-red-600 mt-1">{{ $beyondPctCount ?? 15 }}</h2>
                        </div>
                        <div class="w-12 h-12 bg-red-100 text-red-600 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Additional Placeholder Card -->
                    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Completed RFAs</p>
                            <h2 class="text-3xl font-extrabold text-green-600 mt-1">128</h2>
                        </div>
                        <div class="w-12 h-12 bg-green-100 text-green-600 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Additional Placeholder Card -->
                    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Total Processed</p>
                            <h2 class="text-3xl font-extrabold text-blue-600 mt-1">185</h2>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                    </div>

                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">RFA Status Overview</h3>
                        <canvas id="rfaStatusChart" height="200"></canvas>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly RFA Trends</h3>
                        <canvas id="rfaTrendChart" height="200"></canvas>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Chart JavaScript Initialization -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Doughnut Chart (Status)
            const ctx1 = document.getElementById('rfaStatusChart').getContext('2d');
            new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Beyond PCT', 'Completed'],
                    datasets: [{
                        data: [42, 15, 128],
                        backgroundColor: ['#f59e0b', '#ef4444', '#10b981']
                    }]
                },
                options: { responsive: true }
            });

            // Line Chart (Trends)
            const ctx2 = document.getElementById('rfaTrendChart').getContext('2d');
            new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Submitted RFAs',
                        data: [12, 19, 15, 25, 22, 30],
                        borderColor: '#2563eb',
                        tension: 0.3,
                        fill: false
                    }]
                },
                options: { responsive: true }
            });
        });
    </script>
</body>
</html>