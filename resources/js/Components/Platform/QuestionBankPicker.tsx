import { useState } from 'react';
export type ImportBank = {id:string; name:string; code?:string; course_id?:string|number|null; course_name?:string|null; module_id?:string|number|null; module_name?:string|null; subject_id?:string|null; subject_name?:string|null};
export type ImportCourse = {id:string|number;name:string};
export type ImportModule = {id:string|number;name:string;course_id:string|number};
export default function QuestionBankPicker({ banks, courses = [], moduleOptions = [], courseMode, value, onChange, disabled = false }: {banks:ImportBank[];courses?:ImportCourse[];moduleOptions?:ImportModule[];courseMode:boolean;value:string;onChange:(bank:ImportBank|null)=>void;disabled?:boolean}) {
    const [parent, setParent] = useState('');
    const [module, setModule] = useState('');
    const key = courseMode ? 'course_id' : 'subject_id';
    const labelKey = courseMode ? 'course_name' : 'subject_name';
    const parentValue = (bank:ImportBank) => String(bank[key] ?? '__none');
    const parents = Array.from(new Map([
        ...banks.map(bank => [parentValue(bank), {value:parentValue(bank),label:bank[labelKey] ?? (bank[key] ? String(bank[key]) : courseMode ? 'Without course' : 'Without subject')}] as const),
        ...(courseMode ? courses.map(course => [String(course.id), {value:String(course.id),label:course.name}] as const) : []),
    ]).values()).sort((a,b)=>a.label.localeCompare(b.label));
    const parentBanks = parent ? banks.filter(bank => parentValue(bank) === parent) : [];
    const modules = Array.from(new Map([
        ...parentBanks.filter(bank=>bank.module_id).map(bank=>[String(bank.module_id),{value:String(bank.module_id),label:bank.module_name ?? String(bank.module_id)}] as const),
        ...moduleOptions.filter(item=>String(item.course_id)===parent).map(item=>[String(item.id),{value:String(item.id),label:item.name}] as const),
    ]).values()).sort((a,b)=>a.label.localeCompare(b.label));
    const available = parentBanks.filter(bank => !module || String(bank.module_id ?? '__none') === module);
    const input = 'mt-1 block w-full rounded border-border';
    return <>
        <label className="text-sm font-semibold">{courseMode ? 'Course' : 'Subject'}<select aria-label={courseMode ? 'Import course' : 'Import subject'} className={input} required disabled={disabled} value={parent} onChange={e=>{setParent(e.target.value);setModule('');onChange(null);}}>
            <option value="">Choose {courseMode ? 'course' : 'subject'}</option>{parents.map(option=><option key={option.value} value={option.value}>{option.label}</option>)}
        </select></label>
        {courseMode && <label className="text-sm font-semibold">Module<select aria-label="Import module" className={input} disabled={disabled || !parent} value={module} onChange={e=>{setModule(e.target.value);onChange(null);}}>
            <option value="">All modules in this course</option>{modules.map(option=><option key={option.value} value={option.value}>{option.label}</option>)}
            {parentBanks.some(bank=>!bank.module_id) && <option value="__none">Without module</option>}
        </select></label>}
        <label className="text-sm font-semibold">Question bank<select aria-label="Import question bank" className={input} required disabled={disabled || !parent} value={available.some(bank=>bank.id===value) ? value : ''} onChange={e=>onChange(available.find(bank=>bank.id===e.target.value) ?? null)}>
            <option value="">{parent ? 'Choose question bank' : 'Choose course/subject first'}</option>{available.map(bank=><option key={bank.id} value={bank.id}>{bank.name}{bank.code ? ' ('+bank.code+')' : ''}</option>)}
        </select></label>
        {parent && available.length===0 && <p role="status" className="text-sm text-slate-600">No question banks match this selection. Create a bank for this course/module or ask your administrator to check your bank access.</p>}
    </>;
}
