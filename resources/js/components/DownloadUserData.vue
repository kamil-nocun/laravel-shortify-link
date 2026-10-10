<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { exportMethod } from '@/routes/privacy';
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="Download your data"
            description="A ZIP with your profile, links, domains, payments and consents as CSV files"
        />
        <Button as-child variant="outline" data-test="download-data-button">
            <a :href="exportMethod().url" download>Download my data</a>
        </Button>
    </div>
</template>
