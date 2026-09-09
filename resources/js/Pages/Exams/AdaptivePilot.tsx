import {Head, Link, useForm} from '@inertiajs/react';
import {PageHeader, PortalAppShell} from '@/Components/Platform';
import {Button} from '@/Components/ui/button';
type Props={exam:{id:string;title:string};control:null|{online_enabled:boolean;offline_enabled:boolean;purpose:string};
    rollout:{can_publish:boolean};candidates:{id:string;candidate_number:string;first_name:string;last_name:string}[];
    packages:{id:string;activation_id:number;created_at:string;access:{id:string;registration:string;name:string;access_code:string}[];
        leases:{id:string;candidate_id:string;status:string;error_code:string|null;result:null|{balances:{key:string;original:number;earned:number;penalty:number;remaining:number;closed:number}[];levels:{number:number;earned:number;available:number}[]}}[]}[]};
export default function AdaptivePilot({exam,control,rollout,candidates,packages}:Props) {
    const pack=useForm({activation_id:'',candidate_ids:[] as string[]});
    const reserved=new Set(packages.flatMap(p=>p.access.map(c=>c.id)));
    return <PortalAppShell title="Center delivery"><Head title="Center delivery"/>
        <section className="mx-auto max-w-6xl space-y-5">
            <PageHeader title={exam.title} description="Choose the center and candidates, then review results after the center sends them back."
                actions={<Button asChild variant="secondary"><Link href={'/exams/'+exam.id+'/adaptive'}>Preparation</Link></Button>}/>
            <form className="space-y-3 rounded border bg-white p-4" onSubmit={e=>{e.preventDefault();pack.post('/exams/'+exam.id+'/adaptive/pilot/packages',{preserveScroll:true});}}>
                <h2 className="text-lg font-semibold">Prepare a center package</h2>
                <label className="block">Center activation ID<input required type="number" min="1" className="block rounded border p-2" value={pack.data.activation_id} onChange={e=>pack.setData('activation_id',e.target.value)}/></label>
                <p>Use the assigned center's active activation ID. Once reserved, candidates cannot be moved to another device or cloud progression by deleting their history.</p>
                {!candidates.length&&<p>No assigned candidates. Assign candidates before reserving a package.</p>}
                <div className="max-h-64 overflow-y-auto">{candidates.map(c=><label className="block py-1" key={c.id}>
                    <input type="checkbox" disabled={reserved.has(c.id)} checked={pack.data.candidate_ids.includes(c.id)} onChange={e=>pack.setData('candidate_ids',e.target.checked?[...pack.data.candidate_ids,c.id]:pack.data.candidate_ids.filter(id=>id!==c.id))}/>
                    {' '+c.candidate_number+' — '+c.first_name+' '+c.last_name}{reserved.has(c.id)?' (reserved)':''}
                </label>)}</div>
                {Object.values(pack.errors).map((v,i)=><p key={i} role="alert" className="text-red-700">{v}</p>)}
                <Button disabled={pack.processing||!control?.offline_enabled||!pack.data.candidate_ids.length}>{pack.processing?'Reserving...':'Create center package'}</Button>
            </form>
            <h2 className="text-xl font-semibold">Center packages and learning results</h2>
            {!packages.length&&<p>No packages reserved.</p>}
            {packages.map(p=><article key={p.id} className="space-y-3 rounded border bg-white p-4">
                <h3 className="font-semibold">Package {p.id}</h3><p>Activation {p.activation_id} · {p.created_at}</p>
                <p>On the center server, open adaptive exams and sign in as supervisor. Import this package ID and enable starts when the center is ready.</p>
                <details><summary>Candidate access codes (confidential)</summary><table className="w-full text-left"><thead><tr><th>Candidate</th><th>Registration</th><th>Access code</th></tr></thead><tbody>
                    {p.access.map(c=><tr key={c.id}><td>{c.name}</td><td>{c.registration}</td><td className="font-mono">{c.access_code}</td></tr>)}
                </tbody></table></details>
                {p.leases.map(l=><section key={l.id} className="rounded border p-3"><p>{p.access.find(c=>c.id===l.candidate_id)?.name}: {l.status}{l.error_code?' — '+l.error_code:''}</p>
                    {l.result&&<><p>These learning results have been checked after synchronization.</p><table className="w-full text-left">
                        <thead><tr><th>Area</th><th>Original</th><th>Earned</th><th>Penalty</th><th>Closed</th></tr></thead><tbody>{l.result.balances.map(a=><tr key={a.key}><td>{a.key}</td><td>{a.original/100}</td><td>{a.earned/100}</td><td>{a.penalty/100}</td><td>{a.closed/100}</td></tr>)}</tbody></table></>}
                </section>)}
            </article>)}
        </section>
    </PortalAppShell>;
}
