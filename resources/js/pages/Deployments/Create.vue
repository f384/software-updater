<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

/** form state */
const uninstallFirst = ref(true);
const installArgs = ref('');
const uninstallArgs = ref('');

/** targets */
const hosts = ref<any[]>([]);
const selectedHosts = ref<string[]>([]);
const loadingHosts = ref(false);
/** installer browser state */
const items = ref<any[]>([]);
const currentPath = ref(''); // relative path within root
const selectedInstaller = ref<string | null>(null); // relative file path
const root = ref<string>(''); // absolute root (UNC/local)
const errorMsg = ref<string | null>(null);
const subnet = ref('');
const defaultUsername = ref('PECS');
const defaultPassword = ref('pecs');
/** full path (for display) */
const fullSelectedPath = computed(() => {
    if (!root.value || !selectedInstaller.value) return '';
    const joiner = root.value.endsWith('\\') || root.value.endsWith('/') ? '' : '\\';
    return `${root.value}${joiner}${selectedInstaller.value.replaceAll('/', '\\')}`;
});

/** load directory listing */
const load = async (path = '') => {
    errorMsg.value = null;
    try {
        const { data } = await axios.get('/installers/browse', { params: { path } });
        items.value = data.items;
        currentPath.value = data.current || '';
        root.value = data.root || '';
        selectedInstaller.value = null; // reset when navigating
    } catch (e: any) {
        errorMsg.value = e?.response?.data?.message || e.message || 'Failed to browse installers';
        items.value = [];
        currentPath.value = '';
        root.value = '';
        selectedInstaller.value = null;
    }
};
const scanQuick = async () => {
    loadingHosts.value = true;
    try {
        const { data } = await axios.get('/network/scan', {
            params: { mode: 'quick', resolve: 1, subnet: subnet.value },
        });
        hosts.value = data;
        selectedHosts.value = [];
    } finally {
        loadingHosts.value = false;
    }
};

const scanFull = async () => {
    loadingHosts.value = true;
    try {
        const { data } = await axios.get('/network/scan', {
            params: { mode: 'full', resolve: 1, subnet: subnet.value },
        });
        hosts.value = data;
        selectedHosts.value = [];
    } finally {
        loadingHosts.value = false;
    }
};

onMounted(() => load());

/** navigation */
const goUp = async () => {
    if (!currentPath.value) return;
    const parts = currentPath.value.split(/[\\/]/).filter(Boolean);
    parts.pop();
    await load(parts.join('/'));
};

const goRoot = async () => load('');

/** select file/dir */
const selectItem = async (item: any) => {
    if (item.type === 'dir') {
        await load(item.path);
    } else {
        selectedInstaller.value = item.path; // relative path from root
    }
};

/** submit (no file upload) */
const deploy = () => {
    if (!selectedInstaller.value || !selectedHosts.value.length) {
        alert('Please select installer and target PCs');
        return;
    }

    const chosen = hosts.value
        .filter((h) => selectedHosts.value.includes(h.ip))
        .map((h) => ({
            ...h,
            username: h.username || defaultUsername.value,
            password: h.password || defaultPassword.value,
        }));

    const form = new FormData();
    form.append('installer_rel_path', selectedInstaller.value);
    form.append('uninstall_first', uninstallFirst.value ? '1' : '0');
    form.append('install_args', installArgs.value);
    form.append('uninstall_args', uninstallArgs.value);
    form.append('hosts', JSON.stringify(chosen));

    router.post('/deployments', form);
};
</script>

<template>
    <Head title="New Deployment" />

    <AppLayout>
        <div class="space-y-8">
            <!-- Header -->
            <div>
                <h1 class="mb-2 text-3xl font-bold text-gray-800 dark:text-gray-100">New Deployment</h1>
                <p class="text-gray-600 dark:text-gray-400">Pick an installer from the shared folder and select target PCs</p>
            </div>

            <!-- Installer browser (NO FILE INPUT) -->
            <div class="space-y-4 rounded-xl bg-white p-6 shadow dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Installer</h2>
                    <div class="flex items-center gap-2">
                        <button
                            @click="goRoot"
                            class="cursor-pointer rounded-md bg-gray-200 px-3 py-1.5 text-sm hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600"
                        >
                            ⌂ Root
                        </button>
                        <button
                            @click="goUp"
                            class="cursor-pointer rounded-md bg-gray-200 px-3 py-1.5 text-sm hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600"
                        >
                            ⬆ Up
                        </button>
                    </div>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400">Root: {{ root || '(not set)' }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Path: /{{ currentPath }}</p>
                <p v-if="errorMsg" class="text-sm text-red-600 dark:text-red-400">{{ errorMsg }}</p>

                <ul class="divide-y divide-gray-200 rounded-md border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                    <li
                        v-for="item in items"
                        :key="item.path"
                        class="flex cursor-pointer items-center justify-between px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700"
                        @click="selectItem(item)"
                    >
                        <div class="flex items-center gap-2">
                            <span v-if="item.type === 'dir'">📁</span>
                            <span v-else>📄</span>
                            <span class="text-sm">{{ item.name }}</span>
                        </div>
                        <div v-if="item.type === 'file'" class="text-xs text-gray-500">{{ item.size }}</div>
                    </li>
                </ul>

                <div v-if="selectedInstaller" class="mt-2 text-sm">
                    <div class="text-green-600 dark:text-green-400">Selected (relative): /{{ selectedInstaller }}</div>
                    <div class="text-gray-600 dark:text-gray-300">Full path: {{ fullSelectedPath }}</div>
                </div>

                <div class="mt-4 grid gap-6 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Install args</label>
                        <input
                            v-model="installArgs"
                            type="text"
                            class="w-full rounded-lg border-gray-300 bg-white text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            placeholder="/quiet /norestart"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Uninstall args</label>
                        <input
                            v-model="uninstallArgs"
                            type="text"
                            class="w-full rounded-lg border-gray-300 bg-white text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            placeholder="/x /quiet"
                        />
                    </div>
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <input id="uninstall" type="checkbox" v-model="uninstallFirst" class="h-4 w-4 rounded border-gray-300" />
                    <label for="uninstall" class="text-sm text-gray-700 dark:text-gray-300">Uninstall previous version first</label>
                </div>
            </div>

            <!-- Targets -->
            <div class="space-y-4 rounded-xl bg-white p-6 shadow dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Target PCs</h2>
                    <div class="flex items-center gap-2">
                        <label for="subnet" class="text-sm text-gray-700 dark:text-gray-300">Subnet filter:</label>
                        <input
                            id="subnet"
                            v-model="subnet"
                            type="text"
                            placeholder="192.168.2."
                            class="rounded-lg border-gray-300 bg-white px-2 py-1 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        />
                    </div>

                    <div class="flex gap-2">
                        <button
                            @click="scanQuick"
                            class="cursor-pointer rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            Quick Scan (ARP)
                        </button>
                        <button
                            @click="scanFull"
                            class="cursor-pointer rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            Deep Scan (/24)
                        </button>
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Default Username</label>
                        <input
                            v-model="defaultUsername"
                            type="text"
                            placeholder="Administrator"
                            class="w-full rounded-lg border-gray-300 bg-white text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Default Password</label>
                        <input
                            v-model="defaultPassword"
                            type="password"
                            placeholder="••••••••"
                            class="w-full rounded-lg border-gray-300 bg-white text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        />
                    </div>
                </div>

                <div v-if="loadingHosts" class="flex items-center justify-center py-6 text-gray-600 dark:text-gray-300">
                    <svg class="mr-2 h-5 w-5 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    Scanning network, please wait...
                </div>

                <div v-else-if="hosts.length === 0" class="text-sm text-gray-500 dark:text-gray-400">No PCs found yet. Click "Scan Network".</div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-gray-600 dark:border-gray-700 dark:text-gray-400">
                                <th class="px-4 py-2">Select</th>
                                <th class="px-4">Hostname</th>
                                <th class="px-4">IP</th>
                                <th class="px-4">Username</th>
                                <th class="px-4">Password</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="h in hosts"
                                :key="h.ip"
                                class="border-b border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                            >
                                <td class="px-4 py-2">
                                    <input type="checkbox" v-model="selectedHosts" :value="h.ip" class="h-4 w-4 rounded border-gray-300" />
                                </td>
                                <td class="px-4 text-gray-800 dark:text-gray-200">{{ h.hostname }}</td>
                                <td class="px-4 text-gray-600 dark:text-gray-400">{{ h.ip }}</td>
                                <td class="px-4">
                                    <input
                                        v-model="h.username"
                                        type="text"
                                        class="w-full rounded border-gray-300 bg-white text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        placeholder="Default → {{ defaultUsername }}"
                                    />
                                </td>
                                <td class="px-4">
                                    <input
                                        v-model="h.password"
                                        type="password"
                                        class="w-full rounded border-gray-300 bg-white text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        placeholder="Default"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex justify-end">
                <button
                    @click="deploy"
                    type="button"
                    class="cursor-pointer rounded-lg bg-green-600 px-6 py-2 font-medium text-white hover:bg-green-700"
                >
                    Deploy
                </button>
            </div>
        </div>
    </AppLayout>
</template>
