import { createContext, useContext } from 'react';

type CandidateClientContextValue = {
    centerServerUrl: string;
};

const CandidateClientContext = createContext<CandidateClientContextValue>({
    centerServerUrl: 'http://127.0.0.1:4080',
});

export function useCandidateClient() {
    return useContext(CandidateClientContext);
}

export { CandidateClientContext };
