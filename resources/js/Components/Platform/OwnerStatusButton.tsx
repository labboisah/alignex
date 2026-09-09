import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
export default function OwnerStatusButton({path,status}: {path:string;status:string}) {
    const [busy,setBusy]=useState(false);
    return <Button type="button" variant="secondary" disabled={busy} onClick={()=>{
        if(!window.confirm(status==='active'?'Set this owner inactive? Existing records will be retained.':'Reactivate this owner?'))return;
        setBusy(true);router.patch(path,{status:status==='active'?'inactive':'active'},{preserveScroll:true,onFinish:()=>setBusy(false)});
    }}>{busy?'Saving…':status==='active'?'Deactivate':'Reactivate'}</Button>;
}
