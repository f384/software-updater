<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const isDark = ref(false);

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
});

const toggleDark = () => {
    isDark.value = !isDark.value;
    if (isDark.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
};

// restore from localStorage
onMounted(() => {
    const saved = localStorage.getItem('theme');
    if (saved === 'dark') {
        document.documentElement.classList.add('dark');
        isDark.value = true;
    }
});
</script>

<template>
    <div class="flex min-h-screen bg-gray-100 text-gray-900 dark:bg-gray-900 dark:text-gray-100">
        <!-- Sidebar -->
        <aside class="flex w-64 flex-col bg-white p-6 shadow-md dark:bg-gray-800">
            <h2 class="mb-6 text-xl font-bold">Software Update</h2>

            <nav class="flex-1 space-y-2">
                <Link
                    href="/dashboard"
                    class="block rounded-md px-3 py-2 hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-900 dark:hover:text-blue-300"
                >
                    Dashboard
                </Link>
                <Link
                    href="/deployments/new"
                    class="block rounded-md px-3 py-2 hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-900 dark:hover:text-blue-300"
                >
                    New Deployment
                </Link>
                <Link
                    href="/settings"
                    class="block rounded-md px-3 py-2 hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-900 dark:hover:text-blue-300"
                    >⚙️ Settings
                </Link>
            </nav>

            <!-- Sidebar footer -->
            <div class="mt-auto text-xs">
                <p class="mb-3">
                    Logged in as<br /><span class="font-semibold">{{ $page.props.auth.user.name }}</span>
                </p>
                <button
                    @click="toggleDark"
                    class="rounded-md bg-gray-200 px-3 py-2 text-sm hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600"
                >
                    {{ isDark ? '☀ Light Mode' : '🌙 Dark Mode' }}
                </button>
            </div>
        </aside>

        <!-- Main content -->
        <main class="flex-1 p-8">
            <slot />
        </main>
    </div>
</template>
