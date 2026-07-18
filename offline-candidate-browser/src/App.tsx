import { useEffect, useState } from 'react';
import { MockModeBadge } from './components/MockModeBadge';
import { ExamSessionProvider, useExamSession } from './context/ExamSessionContext';
import { CandidateAlreadyLoggedInPage } from './pages/CandidateAlreadyLoggedInPage';
import { CandidateAlreadySubmittedPage } from './pages/CandidateAlreadySubmittedPage';
import { CandidateLoginPage } from './pages/CandidateLoginPage';
import { DisqualifiedPage } from './pages/DisqualifiedPage';
import { ExamClosedPage } from './pages/ExamClosedPage';
import { ExamInstructionsPage } from './pages/ExamInstructionsPage';
import { ExamNotActivePage } from './pages/ExamNotActivePage';
import { ExamScreenPage } from './pages/ExamScreenPage';
import { ServerConfigurationPage } from './pages/ServerConfigurationPage';
import { ServerConnectionErrorPage } from './pages/ServerConnectionErrorPage';
import { SubmitFailedRetryPage } from './pages/SubmitFailedRetryPage';
import { SubmittedSuccessfullyPage } from './pages/SubmittedSuccessfullyPage';
import { TimeExpiredSubmittingPage } from './pages/TimeExpiredSubmittingPage';

type AppScreen =
    | 'server-configuration'
    | 'candidate-login'
    | 'exam-instructions'
    | 'exam-screen'
    | 'submitted'
    | 'disqualified'
    | 'server-connection-error'
    | 'candidate-already-submitted'
    | 'candidate-already-logged-in'
    | 'exam-not-active'
    | 'exam-closed'
    | 'time-expired-submitting'
    | 'submit-failed-retry';

export function App() {
    return (
        <ExamSessionProvider>
            <MockModeBadge />
            <AppRoutes />
        </ExamSessionProvider>
    );
}

function AppRoutes() {
    const { attempt_token, clearExamSession, clearLoginSession, restoreSession, submission_status, submission_summary } = useExamSession();
    const [screen, setScreen] = useState<AppScreen>('server-configuration');
    const [disqualificationReason, setDisqualificationReason] = useState<string | undefined>();

    useEffect(() => {
        if (attempt_token) {
            void restoreSession();
        }
    }, [attempt_token, restoreSession]);

    useEffect(() => {
        if (attempt_token && screen === 'candidate-login') {
            setScreen('exam-instructions');
        }
    }, [attempt_token, screen]);

    useEffect(() => {
        if ((submission_status === 'submitted' || submission_status === 'auto_submitted') && submission_summary) {
            setScreen('submitted');
        }
    }, [submission_status, submission_summary]);

    if (screen === 'server-configuration') {
        return <ServerConfigurationPage onContinue={() => setScreen('candidate-login')} />;
    }

    if (screen === 'candidate-login') {
        return <CandidateLoginPage onChangeServer={() => setScreen('server-configuration')} onLoginSuccess={() => setScreen('exam-instructions')} onRecovery={(nextScreen) => setScreen(nextScreen)} />;
    }

    if (screen === 'exam-instructions') {
        return (
            <ExamInstructionsPage
                onBackToLogin={() => {
                    clearLoginSession();
                    setScreen('candidate-login');
                }}
                onDisqualified={(reason) => {
                    setDisqualificationReason(reason);
                    clearLoginSession();
                    setScreen('disqualified');
                }}
                onStartExam={() => setScreen('exam-screen')}
            />
        );
    }

    if (screen === 'exam-screen') {
        return (
            <ExamScreenPage
                onDisqualified={(reason) => {
                    setDisqualificationReason(reason);
                    clearLoginSession();
                    setScreen('disqualified');
                }}
                onExamClosed={() => {
                    clearLoginSession();
                    setScreen('exam-closed');
                }}
                onSubmitFailed={() => setScreen('submit-failed-retry')}
                onSubmitted={() => setScreen('submitted')}
            />
        );
    }

    if (screen === 'submitted') {
        return (
            <SubmittedSuccessfullyPage
                summary={submission_summary}
                onDone={() => {
                    clearExamSession();
                    setScreen('candidate-login');
                }}
            />
        );
    }

    if (screen === 'disqualified') {
        return (
            <DisqualifiedPage
                reason={disqualificationReason}
                onDone={() => {
                    clearExamSession();
                    setScreen('candidate-login');
                }}
            />
        );
    }

    if (screen === 'server-connection-error') {
        return <ServerConnectionErrorPage onBackToLogin={() => setScreen('candidate-login')} onRetry={() => setScreen('server-configuration')} />;
    }

    if (screen === 'candidate-already-submitted') {
        return <CandidateAlreadySubmittedPage onBackToLogin={() => setScreen('candidate-login')} />;
    }

    if (screen === 'candidate-already-logged-in') {
        return <CandidateAlreadyLoggedInPage onBackToLogin={() => setScreen('candidate-login')} onRetry={() => setScreen('candidate-login')} />;
    }

    if (screen === 'exam-not-active') {
        return <ExamNotActivePage onBackToLogin={() => setScreen('candidate-login')} onRetry={() => setScreen('candidate-login')} />;
    }

    if (screen === 'exam-closed') {
        return <ExamClosedPage onBackToLogin={() => setScreen('candidate-login')} />;
    }

    if (screen === 'time-expired-submitting') {
        return <TimeExpiredSubmittingPage />;
    }

    if (screen === 'submit-failed-retry') {
        return <SubmitFailedRetryPage onBackToLogin={() => setScreen('candidate-login')} onRetry={() => setScreen('exam-screen')} />;
    }

    return <ServerConfigurationPage onContinue={() => setScreen('candidate-login')} />;
}
