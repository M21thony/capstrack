import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/legacy.js',
                'resources/js/main.jsx',
                'resources/js/student-review.jsx',
                'resources/js/milestones/tracker.js',
                'resources/js/submission-preview.js',
                'resources/css/defenses.css',
                'resources/js/defenses/panel-assignment.js',
                'resources/js/defenses/defense-scheduling.js',
                'resources/css/financial.css',
                'resources/js/financial/capsa.js',
                'resources/js/financial/student.js',
                'resources/student-ui/styles.css',
                'resources/student-ui/student.js',
                'resources/student-ui/Styles/dashboard.css',
                'resources/student-ui/Styles/personal-info.css',
                'resources/student-ui/Styles/team.css',
                'resources/student-ui/Styles/experts.css',
                'resources/student-ui/Styles/submission.css',
                'resources/student-ui/Styles/consultation.css',
                'resources/student-ui/Styles/template.css',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        react(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});