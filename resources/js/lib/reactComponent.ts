import { ComponentType } from 'react';

const memoSymbol = Symbol.for('react.memo');
const forwardRefSymbol = Symbol.for('react.forward_ref');

export function isRenderableComponent(value: unknown): value is ComponentType<any> {
    if (typeof value === 'function') {
        return true;
    }

    if (!value || typeof value !== 'object') {
        return false;
    }

    const reactType = (value as { $$typeof?: symbol }).$$typeof;

    return reactType === memoSymbol || reactType === forwardRefSymbol;
}
