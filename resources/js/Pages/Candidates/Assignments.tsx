import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Search, Trash2, UserPlus } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';
import { DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Candidate, ExamOption } from './types';

type StudentRow = { id: string | number; full_name: string; registration_number: string; class_name?: string | null; status: string };
type StudentGroup = { id: string | number; name: string; class_name?: string | null; students_count: number; students: StudentRow[] };

export default function CandidateAssignments({ exams, selectedExam, candidates, assignedCandidates, studentGroups = [], assignedStudents = [] }: { exams: { data: ExamOption[] }; selectedExam?: { data: ExamOption } | null; candidates: { data: Candidate[] }; assignedCandidates: { data: Candidate[] }; studentGroups?: StudentGroup[]; assignedStudents?: StudentRow[] }) {
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<string[]>([]);
    const exam = selectedExam?.data ?? null;
    const isSecondaryExam = exam?.owner_context === 'secondary_school';
    const assignedIds = useMemo(() => new Set(assignedCandidates.data.map((candidate) => candidate.id)), [assignedCandidates.data]);
    const availableCandidates = candidates.data.filter((candidate) => !assignedIds.has(candidate.id));
    const filteredCandidates = availableCandidates.filter((candidate) => {
        const value = `${candidate.full_name} ${candidate.registration_number} ${candidate.email ?? ''}`.toLowerCase();
        return value.includes(search.toLowerCase());
    });

    const { data, setData, post, processing, reset } = useForm<{ exam_id: string; candidate_ids: string[]; student_group_id: string }>({
        exam_id: exam?.id ?? '',
        candidate_ids: [],
        student_group_id: '',
    });
    const selectedGroup = studentGroups.find((group) => String(group.id) === data.student_group_id) ?? null;

    const toggle = (candidateId: string) => {
        const next = selected.includes(candidateId) ? selected.filter((id) => id !== candidateId) : [...selected, candidateId];
        setSelected(next);
        setData('candidate_ids', next);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/candidates/assign', {
            preserveScroll: true,
            onSuccess: () => {
                setSelected([]);
                reset('candidate_ids', 'student_group_id');
            },
        });
    };

    const changeExam = (examId: string) => {
        setData('exam_id', examId);
        setSelected([]);
        router.visit(`/candidates/assignments${examId ? `?exam_id=${examId}` : ''}`, { preserveScroll: true });
    };

    return (
        <PortalAppShell title={isSecondaryExam ? 'Assign Students' : 'Assign Candidates'}>
            <Head title={isSecondaryExam ? 'Assign Students' : 'Assign Candidates'} />
            <PageHeader eyebrow="Exam Registration" title={isSecondaryExam ? 'Assign Student Group to Exam' : 'Assign Candidates to Exam'} description={isSecondaryExam ? 'Select a student group and review the students that will sit for this exam.' : 'Select an exam, find candidates, and manage exam candidate assignments.'} actions={<Button asChild type="button" variant="secondary"><Link href="/candidates"><ArrowLeft className="h-4 w-4" />Back</Link></Button>} />

            <form onSubmit={submit} className="mb-5 rounded-md border border-border bg-white p-4 shadow-sm">
                <div className="grid gap-3 lg:grid-cols-[1fr_1fr_auto] lg:items-end">
                    <label className="text-sm font-semibold text-slateDark">
                        Select Exam
                        <select className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm" value={data.exam_id} onChange={(event) => changeExam(event.target.value)} required>
                            <option value="">Choose exam</option>
                            {exams.data.map((exam) => <option key={exam.id} value={exam.id}>{exam.title} ({exam.exam_code})</option>)}
                        </select>
                    </label>
                    {isSecondaryExam ? (
                        <label className="text-sm font-semibold text-slateDark">
                            Student Group
                            <select className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm" value={data.student_group_id} onChange={(event) => setData('student_group_id', event.target.value)} required>
                                <option value="">Choose group</option>
                                {studentGroups.map((group) => <option key={group.id} value={String(group.id)}>{group.name}{group.class_name ? ` (${group.class_name})` : ''} - {group.students_count} students</option>)}
                            </select>
                        </label>
                    ) : (
                        <label className="text-sm font-semibold text-slateDark">
                            Search Candidates
                            <span className="relative mt-1 block">
                                <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                <input className="block h-10 w-full rounded-md border-border pl-9 shadow-sm focus:border-primary focus:ring-primary sm:text-sm" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Name, registration number, or email" />
                            </span>
                        </label>
                    )}
                    <Button type="submit" disabled={processing || !data.exam_id || (isSecondaryExam ? !data.student_group_id : selected.length === 0)}><UserPlus className="h-4 w-4" />{isSecondaryExam ? `Assign Group (${selectedGroup?.students_count ?? 0})` : `Assign Selected (${selected.length})`}</Button>
                </div>
            </form>

            {isSecondaryExam ? (
                <div className="grid gap-5 xl:grid-cols-2">
                    <section>
                        <h2 className="mb-3 font-semibold text-slateDark">{selectedGroup ? `${selectedGroup.name} Students` : 'Group Students'}</h2>
                        <DataTable<StudentRow>
                            rows={selectedGroup?.students ?? []}
                            emptyTitle="Select a group to preview students"
                            columns={[
                                { key: 'full_name', header: 'Full Name', render: (student) => <span className="font-semibold">{student.full_name}</span> },
                                { key: 'registration_number', header: 'Admission Number' },
                                { key: 'class_name', header: 'Class', render: (student) => student.class_name ?? 'N/A' },
                                { key: 'status', header: 'Status', render: (student) => <StatusBadge label={student.status} tone={student.status === 'active' ? 'success' : 'neutral'} /> },
                            ]}
                        />
                    </section>

                    <section>
                        <h2 className="mb-3 font-semibold text-slateDark">Assigned Students {exam ? `for ${exam.title}` : ''}</h2>
                        <DataTable<StudentRow>
                            rows={assignedStudents}
                            emptyTitle="No assigned students"
                            columns={[
                                { key: 'full_name', header: 'Full Name', render: (student) => <span className="font-semibold">{student.full_name}</span> },
                                { key: 'registration_number', header: 'Admission Number' },
                                { key: 'class_name', header: 'Class', render: (student) => student.class_name ?? 'N/A' },
                                { key: 'status', header: 'Status', render: (student) => <StatusBadge label={student.status} tone={student.status === 'active' ? 'success' : 'neutral'} /> },
                            ]}
                        />
                    </section>
                </div>
            ) : (
            <div className="grid gap-5 xl:grid-cols-2">
                <section>
                    <h2 className="mb-3 font-semibold text-slateDark">Available Candidates</h2>
                    <DataTable<Candidate>
                        rows={filteredCandidates}
                        emptyTitle="No available candidates"
                        columns={[
                            { key: 'select', header: 'Select', render: (candidate) => <input type="checkbox" checked={selected.includes(candidate.id)} onChange={() => toggle(candidate.id)} /> },
                            { key: 'full_name', header: 'Full Name', render: (candidate) => <span className="font-semibold">{candidate.full_name}</span> },
                            { key: 'registration_number', header: 'Registration Number' },
                            { key: 'status', header: 'Status', render: (candidate) => <StatusBadge label={candidate.status_label} tone={candidate.status === 'active' ? 'success' : candidate.status === 'suspended' ? 'danger' : 'neutral'} /> },
                        ]}
                    />
                </section>

                <section>
                    <h2 className="mb-3 font-semibold text-slateDark">Assigned Candidates {exam ? `for ${exam.title}` : ''}</h2>
                    <DataTable<Candidate>
                        rows={assignedCandidates.data}
                        emptyTitle="No assigned candidates"
                        columns={[
                            { key: 'full_name', header: 'Full Name', render: (candidate) => <span className="font-semibold">{candidate.full_name}</span> },
                            { key: 'registration_number', header: 'Registration Number' },
                            { key: 'email', header: 'Email', render: (candidate) => candidate.email ?? 'N/A' },
                            {
                                key: 'actions',
                                header: 'Actions',
                                render: (candidate) => exam && (
                                    <Button type="button" variant="danger" onClick={() => window.confirm('Remove candidate from this exam?') && router.delete(`/exams/${exam.id}/candidates/${candidate.id}`, { preserveScroll: true })}>
                                        <Trash2 className="h-4 w-4" />
                                        Remove
                                    </Button>
                                ),
                            },
                        ]}
                    />
                </section>
            </div>
            )}
        </PortalAppShell>
    );
}
