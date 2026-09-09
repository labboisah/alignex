import StructurePage from '@/Components/Platform/StructurePage';
export default function Courses({professionalSchool, courses, programmes = [], canManageStructure = true}: any) {
    return <StructurePage title="Courses" owner={professionalSchool.name} path={'/professional-schools/'+professionalSchool.id+'/courses'} rows={courses} canManage={canManageStructure} defaults={{ name:'', code:'', description:'', status:'active',programme_id:'' }} fields={[{name:'name',label:'Name',required:true},{name:'code',label:'Code'},{name:'description',label:'Description',type:'textarea'},{name:'programme_id',label:'Programme',required:true,options:programmes.map((r:any)=>({value:String(r.id),label:r.name}))}]} />;
}
