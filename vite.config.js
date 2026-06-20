import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
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
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
