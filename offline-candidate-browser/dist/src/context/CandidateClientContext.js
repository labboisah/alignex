import { createContext, useContext } from 'react';
const CandidateClientContext = createContext({
    centerServerUrl: 'http://127.0.0.1:4080',
});
export function useCandidateClient() {
    return useContext(CandidateClientContext);
}
export { CandidateClientContext };
