<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    deployments: Array<any>;
}>();
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold">Dashboard</h1>
            <Link href="/deployments/new" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"> + New Deployment </Link>
        </div>

        <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <h2 class="mb-4 text-lg font-semibold">Recent Deployments</h2>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-gray-600 dark:text-gray-400">
                        <th class="py-2">ID</th>
                        <th>Installer</th>
                        <th>Status</th>
                        <th>Targets</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in deployments" :key="d.id" class="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                        <td class="py-2">
                            <Link :href="`/deployments/${d.id}`" class="text-blue-600 hover:underline"> #{{ d.id }} </Link>
                        </td>

                        <td>{{ d.installer_original_name }}</td>
                        <td>
                            <span
                                :class="{
                                    'text-gray-500': d.status === 'planned',
                                    'text-blue-600': d.status === 'running',
                                    'text-green-600': d.status === 'completed',
                                    'text-red-600': d.status === 'failed',
                                }"
                            >
                                {{ d.status }}
                            </span>
                        </td>

                        <td>{{ d.total_targets }}</td>
                        <td>{{ new Date(d.created_at).toLocaleString() }}</td>
                    </tr>
                    <tr v-if="deployments.length === 0">
                        <td colspan="5" class="py-4 text-center text-gray-500 dark:text-gray-400">No deployments yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
