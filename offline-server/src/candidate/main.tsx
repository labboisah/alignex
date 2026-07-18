import React from 'react';
import ReactDOM from 'react-dom/client';
import { CandidateApp } from './CandidateApp';
import '../renderer/styles.css';

ReactDOM.createRoot(document.getElementById('candidate-root') as HTMLElement).render(
    <React.StrictMode>
        <CandidateApp />
    </React.StrictMode>,
);
