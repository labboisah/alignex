import { RecordEditor, statusField } from '@/Components/Platform/RecordEditor';
export default function InstitutionForm({ institution }: { institution?: Record<string, any> }) {
    return <RecordEditor title={institution ? 'Edit Institution' : 'New Institution'} path={institution ? '/institutions/' + institution.id : '/institutions'} method={institution ? 'patch' : 'post'}
        values={{ name: '', code: '', institution_type: '', email: '', phone: '', address: '', description: '', status: 'active', ...institution }}
        fields={[{name:'name',label:'Name',required:true},{name:'code',label:'Code'},{name:'institution_type',label:'Institution type',required:true},{name:'email',label:'Email',type:'email',required:true},{name:'phone',label:'Phone'},{name:'address',label:'Address'},{name:'description',label:'Description',type:'textarea'},statusField]} />;
}
