import { useExamSession } from '../context/ExamSessionContext';

export function useExamQuestionState() {
    const { questions, selected_answers, current_question_index, setCurrentQuestion, selectAnswerLocally } = useExamSession();

    return {
        questions,
        selectedAnswers: selected_answers,
        currentQuestionIndex: current_question_index,
        currentQuestion: questions[current_question_index] ?? null,
        setCurrentQuestion,
        selectAnswerLocally,
    };
}
