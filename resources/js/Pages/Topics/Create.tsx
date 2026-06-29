import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { TopicForm } from './Form';
import { StatusOption, SubjectOption, Topic } from './types';

export default function CreateTopic({ subjects, topics, statuses }: { subjects: { data: SubjectOption[] }; topics: { data: Topic[] }; statuses: StatusOption[] }) {
    return (
        <PortalAppShell title="Create Topic">
            <Head title="Create Topic" />
            <section className="mx-auto max-w-4xl">
                <PageHeader eyebrow="Topics" title="Create Topic" description="Add a topic under a subject." />
                <TopicForm subjects={subjects} topics={topics} statuses={statuses} submitLabel="Create Topic" />
            </section>
        </PortalAppShell>
    );
}
