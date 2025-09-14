<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

// declare props
const props = defineProps<{
    installer_root: string;
}>();

// initialize form with prop
const form = useForm({
    installer_root: props.installer_root || '',
});
</script>

<template>
    <Head title="Settings" />

    <AppLayout>
        <div class="space-y-8">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Settings</h1>

            <div class="max-w-lg rounded-xl bg-white p-6 shadow dark:bg-gray-800">
                <form @submit.prevent="form.post('/settings')">
                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"> Installer Root Path </label>
                        <input
                            v-model="form.installer_root"
                            type="text"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            placeholder="e.g. \\\\FilipPC\\Installers"
                        />
                    </div>

                    <button
                        type="submit"
                        class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
                        :disabled="form.processing"
                    >
                        Save
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
