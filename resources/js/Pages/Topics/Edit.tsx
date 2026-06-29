import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { TopicForm } from './Form';
import { StatusOption, SubjectOption, Topic } from './types';

export default function EditTopic({ topic, subjects, topics, statuses }: { topic: { data: Topic }; subjects: { data: SubjectOption[] }; topics: { data: Topic[] }; statuses: StatusOption[] }) {
    return (
        <PortalAppShell title="Edit Topic">
            <Head title="Edit Topic" />
            <section className="mx-auto max-w-4xl">
                <PageHeader eyebrow="Topics" title="Edit Topic" description="Update topic assignment, hierarchy, and status." />
                <TopicForm topic={topic.data} subjects={subjects} topics={topics} statuses={statuses} submitLabel="Save Changes" />
            </section>
        </PortalAppShell>
    );
}
