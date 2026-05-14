<template>
    <GuestLayout>
        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="email" class="label">Email</label>
                <input id="email" v-model="form.email" type="email" autocomplete="email" required class="input mt-1" />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="label">Password</label>
                <input id="password" v-model="form.password" type="password" autocomplete="current-password" required class="input mt-1" />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" v-model="form.remember" class="rounded border-slate-300 text-brand focus:ring-brand" />
                    Remember me
                </label>
            </div>

            <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                Sign in
            </button>
        </form>
    </GuestLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login');
}
</script>
