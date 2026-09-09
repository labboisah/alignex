import { Head, Link, useForm } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
type Area={area_key:string;label?:string;question_count:number;available:number;required:number};
type Props={exam:{id:string;title:string};readiness:{ready:boolean;warnings:string[];areas:Area[];item_count:number}};
export default function AdaptivePreparation({exam,readiness}:Props) {
    const {post,processing,errors}=useForm({});
    const friendly=(text:string)=>readiness.areas.reduce((value,area)=>value.replaceAll(area.area_key,area.label??'Selected subject'),text);
    return <PortalAppShell title="Question readiness"><Head title="Question readiness"/>
        <section className="mx-auto max-w-5xl space-y-5">
            <PageHeader title={exam.title} description="We check and prepare your questions automatically when you save the exam." actions={<Button asChild variant="secondary"><Link href={'/exams/'+exam.id}>Back to exam</Link></Button>}/>
            <div role="status" className="rounded border bg-white p-5"><h2 className="font-semibold">{readiness.ready?'Your questions are ready':'Add or update questions before candidates start'}</h2>
                <p>{readiness.item_count} approved questions are available for adaptive selection.</p>
                {readiness.warnings.length>0&&<ul className="list-disc pl-5">{readiness.warnings.map((warning,index)=><li key={index}>{friendly(warning)}</li>)}</ul>}
            </div>
            {Object.values(errors).length>0&&<p role="alert">{Object.values(errors).join(' ')}</p>}
            <table className="w-full rounded border bg-white text-left"><thead><tr><th>Subject or area</th><th>Questions per level</th><th>Needed for all levels</th><th>Available</th></tr></thead><tbody>
                {readiness.areas.map(area=><tr key={area.area_key}><td>{area.label??'Selected subject'}</td><td>{area.question_count}</td><td>{area.required}</td><td>{area.available}</td></tr>)}
            </tbody></table>
            <div className="flex gap-3"><Button asChild variant="secondary"><Link href={'/exams/'+exam.id+'/edit'}>Edit exam settings</Link></Button>
                <Button disabled={processing} onClick={()=>post('/exams/'+exam.id+'/adaptive/prepare',{preserveScroll:true})}>{processing?'Checking...':'Check questions again'}</Button></div>
        </section>
    </PortalAppShell>;
}
