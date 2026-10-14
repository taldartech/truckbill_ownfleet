<template>
    <BulkUploadModal v-bind="$attrs">
        <template v-for="(_, slotName) in $slots" #[slotName]="slotProps">
            <slot :name="slotName" v-bind="slotProps ?? {}" />
        </template>
    </BulkUploadModal>
</template>

<script setup>
import BulkUploadModal from './ui/BulkUploadModal.vue';
</script>
