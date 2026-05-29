<template>
    <footer class="app-footer">
        <div class="float-end d-none d-sm-inline">
            <a href="https://www.taldartechconsultancy.com/" target="_blank" rel="noopener">
                Developed &amp; Consulted by Taldar Tech Consultancy.
            </a>
        </div>
        <strong>Copyright All Rights Reserved © {{ year }}</strong>
    </footer>
</template>

<script setup>
const year = new Date().getFullYear();
</script>
