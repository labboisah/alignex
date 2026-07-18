import { io } from 'socket.io-client';
export function createCandidateSocket(baseUrl = 'http://127.0.0.1:4080') {
    return io(baseUrl, {
        autoConnect: false,
        transports: ['websocket'],
    });
}
