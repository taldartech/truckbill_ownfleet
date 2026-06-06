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

            <form method="POST" action="/login" @submit.prevent="handleLogin">
                <input type="hidden" name="_token" :value="csrfToken" />

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
                        />
                        <button
                            type="button"
                            class="password-toggle"
                            tabindex="-1"
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
                            name="remember"
                            class="form-check-input"
                            type="checkbox"
                            value="1"
                            :checked="remember"
                            @change="remember = $event.target.checked"
                        />
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <a href="/forgot-password" class="link-primary">Forgot password?</a>
                </div>

                <button type="submit" class="btn-auth" :disabled="submitting">
                    {{ submitting ? 'Signing in…' : 'Sign In' }}
                    <i v-if="!submitting" class="fas fa-arrow-right" style="margin-left: 8px"></i>
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { clearClientAuthState, currentCsrfToken, refreshCsrfToken } from '../api';

const logoUrl = window.__LOGO_URL__ ?? '/logo.png';
const email = ref('');
const password = ref('');
const error = ref('');
const status = ref('');
const remember = ref(false);
const showPassword = ref(false);
const submitting = ref(false);
const csrfToken = ref(currentCsrfToken());

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

    const token = await refreshCsrfToken();

    if (token) {
        csrfToken.value = token;
    }
});

async function handleLogin(event) {
    error.value = '';
    submitting.value = true;

    try {
        const token = (await refreshCsrfToken()) || currentCsrfToken();

        if (!token) {
            error.value = 'Your session expired. Please refresh the page and sign in again.';

            return;
        }

        const tokenInput = event.target.querySelector('input[name="_token"]');

        if (tokenInput) {
            tokenInput.value = token;
        }

        event.target.submit();
    } catch {
        error.value = 'Unable to sign in. Please refresh the page and try again.';
    } finally {
        submitting.value = false;
    }
}
</script>
