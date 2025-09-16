<script setup lang="ts">
import { echo } from '@/echo';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

// ----------------------
// Types
// ----------------------
type DeploymentTarget = {
    id: number;
    ip: string;
    hostname: string | null;
    status: 'pending' | 'running' | 'success' | 'failed';
    message?: string | null;
};

type Deployment = {
    id: number;
    installer_original_name: string;
    status: 'planned' | 'running' | 'completed' | 'failed';
    total_targets: number;
    created_at: string;
    progress?: number;
    targets?: DeploymentTarget[];
};

// ----------------------
// Props
// ----------------------
const props = defineProps<{
    deployments: Deployment[];
}>();

// ----------------------
// Reactive local state
// ----------------------
const localDeployments = ref<Deployment[]>([]);

// ----------------------
// Lifecycle
// ----------------------
onMounted(() => {
    // deep clone props to make reactive
    localDeployments.value = JSON.parse(JSON.stringify(props.deployments));

    // subscribe to each deployment channel
    localDeployments.value.forEach((d) => {
        echo.channel(`deployment.${d.id}`).listen('DeploymentTargetUpdated', (e: DeploymentTarget) => {
            const deployment = localDeployments.value.find((dep) => dep.id === d.id);
            if (!deployment) return;

            if (!deployment.targets) deployment.targets = [];

            const idx = deployment.targets.findIndex((t) => t.id === e.id);
            if (idx !== -1) {
                deployment.targets[idx] = e;
            } else {
                deployment.targets.push(e);
            }

            // recalc progress
            const total = deployment.total_targets;
            const done = deployment.targets.filter((t: DeploymentTarget) => ['success', 'failed'].includes(t.status)).length ?? 0;

            deployment.progress = Math.round((done / total) * 100);

            // if all done -> mark deployment as completed
            if (done === total) {
                deployment.status = 'completed';
            }
        });
    });
});
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
                        <th>Progress</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in localDeployments" :key="d.id" class="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
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

                        <td class="w-48">
                            <div class="h-3 w-full rounded bg-gray-200 dark:bg-gray-700">
                                <div class="h-3 rounded bg-blue-600 transition-all" :style="{ width: (d.progress || 0) + '%' }"></div>
                            </div>
                            <div class="mt-1 text-xs text-gray-500">
                                {{ d.progress || 0 }}% ({{ d.targets?.filter((t) => t.status === 'success').length || 0 }}/{{ d.total_targets }})
                            </div>
                        </td>

                        <td>
                            {{ new Date(d.created_at).toLocaleString() }}
                        </td>
                    </tr>

                    <tr v-if="localDeployments.length === 0">
                        <td colspan="5" class="py-4 text-center text-gray-500 dark:text-gray-400">No deployments yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
