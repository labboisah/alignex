import StructurePage from '@/Components/Platform/StructurePage';
export default function Arms({secondarySchool, classes, arms, basePath}: any) {
    return <StructurePage title="Class arms" owner={secondarySchool.name} path={basePath ?? '/secondary-schools/'+secondarySchool.id+'/arms'} rows={arms}
        defaults={{school_class_id:'',name:'',status:'active'}}
        fields={[{name:'name',label:'Arm name',required:true},{name:'school_class_id',label:'Class',required:true,options:classes.map((r:any)=>({value:String(r.id),label:r.name}))}]} />;
}
