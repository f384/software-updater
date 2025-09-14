<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    deployment: {
        id: number;
        installer_original_name: string;
        installer_stored_path: string;
        uninstall_first: boolean;
        status: string;
        options: Record<string, string>;
        targets: Array<{
            id: number;
            hostname: string;
            status: string;
            logs?: string | null;
        }>;
        created_at: string;
    };
}>();
</script>

<template>
    <Head title="Deployment Details" />

    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div>
                <h1 class="mb-2 text-2xl font-bold">Deployment #{{ deployment.id }}</h1>
                <p class="text-gray-600 dark:text-gray-400">
                    Installer: <strong>{{ deployment.installer_original_name }}</strong>
                    <br />
                    Path: <span class="text-sm text-gray-500">{{ deployment.installer_stored_path }}</span>
                    <br />
                    Status: <span class="font-medium">{{ deployment.status }}</span>
                    <br />
                    Created: {{ new Date(deployment.created_at).toLocaleString() }}
                </p>
            </div>

            <!-- Targets -->
            <div class="rounded-xl bg-white p-6 shadow dark:bg-gray-800">
                <h2 class="mb-4 text-lg font-semibold">Target PCs</h2>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-600 dark:text-gray-400">
                            <th class="py-2">Hostname</th>
                            <th>Status</th>
                            <th>Logs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in deployment.targets" :key="t.id" class="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                            <td class="py-2">{{ t.hostname }}</td>
                            <td>
                                <span
                                    :class="{
                                        'text-gray-500': t.status === 'pending',
                                        'text-blue-600': t.status === 'running',
                                        'text-green-600': t.status === 'completed',
                                        'text-red-600': t.status === 'failed',
                                    }"
                                >
                                    {{ t.status }}
                                </span>
                            </td>
                            <td class="text-xs whitespace-pre-wrap text-gray-500">
                                {{ t.logs || '-' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
