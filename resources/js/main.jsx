import { createRoot } from 'react-dom/client';
import App from './App';

const el = document.getElementById('pdf-annotator-root');
if (el) {
    createRoot(el).render(<App />);
}