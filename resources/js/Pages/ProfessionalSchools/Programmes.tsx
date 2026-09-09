import StructurePage from '@/Components/Platform/StructurePage';
export default function Programmes({professionalSchool, programmes, canManageStructure = true}: any) {
    return <StructurePage title="Programmes" owner={professionalSchool.name} path={'/professional-schools/'+professionalSchool.id+'/programmes'} rows={programmes} canManage={canManageStructure} defaults={{ name:'', code:'', description:'', status:'active',duration:'' }} fields={[{name:'name',label:'Name',required:true},{name:'code',label:'Code'},{name:'description',label:'Description',type:'textarea'},{name:'duration',label:'Duration'}]} />;
}
