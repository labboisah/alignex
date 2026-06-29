import { Lookup } from './Self';

export default function VerifyResult() {
    return <Lookup title="Verify Result" endpoint="/api/results/verify" fields={['hash']} />;
}
