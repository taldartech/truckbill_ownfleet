<template>
    <div class="vue-auth-page">
        <div class="auth-card">
            <div class="logo-section">
                <img :src="logoUrl" alt="TruckBill Logo" class="brand-image" />
            </div>

            <h2 class="page-title">Welcome back! Please sign in to continue.</h2>

            <div v-if="status" class="alert-success" role="alert">
                {{ status }}
            </div>

            <div v-if="error" class="alert-danger" role="alert">
                {{ error }}
            </div>

            <form novalidate @submit.prevent="handleLogin">
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <input
                            id="email"
                            v-model="email"
                            type="email"
                            name="email"
                            class="form-control"
                            required
                            autofocus
                            autocomplete="email"
                            placeholder="name@company.com"
                            :disabled="loading"
                        />
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input
                            id="password"
                            v-model="password"
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            class="form-control"
                            required
                            autocomplete="current-password"
                            placeholder="Enter your password"
                            :disabled="loading"
                        />
                        <button
                            type="button"
                            class="password-toggle"
                            tabindex="-1"
                            :disabled="loading"
                            @click="showPassword = !showPassword"
                        >
                            <i :class="showPassword ? 'fas fa-eye-slash' : 'far fa-eye'"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <div class="form-check">
                        <input
                            id="remember"
                            v-model="remember"
                            class="form-check-input"
                            type="checkbox"
                            :disabled="loading"
                        />
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <a href="/forgot-password" class="link-primary">Forgot password?</a>
                </div>

                <button type="submit" class="btn-auth" :disabled="loading">
                    {{ loading ? 'Signing in…' : 'Sign In' }}
                    <i v-if="!loading" class="fas fa-arrow-right" style="margin-left: 8px"></i>
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { clearClientAuthState, login } from '../api';

const logoUrl = window.__LOGO_URL__ ?? '/logo.png';
const email = ref('');
const password = ref('');
const error = ref('');
const status = ref('');
const remember = ref(false);
const showPassword = ref(false);
const loading = ref(false);

const route = useRoute();

onMounted(async () => {
    if (route.query.logged_out === '1') {
        clearClientAuthState();
        status.value = 'You have been signed out.';
    }

    const flash = window.__LOGIN_FLASH__ ?? {};

    if (flash.email) {
        email.value = flash.email;
    }

    if (flash.error) {
        error.value = flash.error;
    }

    if (flash.status) {
        status.value = flash.status;
    }

    if (flash.remember) {
        remember.value = true;
    }
});

async function handleLogin() {
    error.value = '';
    loading.value = true;

    try {
        const data = await login(email.value.trim(), password.value, remember.value);

        window.location.assign(data.redirect ?? '/app/dashboard');
    } catch (loginError) {
        if (loginError.response?.status === 422) {
            error.value =
                loginError.response.data?.errors?.email?.[0] ??
                loginError.response.data?.message ??
                'These credentials do not match our records.';
        } else if (loginError.response?.status === 419) {
            error.value = 'Your session expired. Please refresh the page and sign in again.';
        } else {
            error.value = 'Unable to sign in. Please try again.';
        }
    } finally {
        loading.value = false;
    }
}
</script>
