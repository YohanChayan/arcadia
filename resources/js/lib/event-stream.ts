/**
 * Reads a `text/event-stream` response body and hands over each event as it completes.
 *
 * `EventSource` would do this for free, but it can only GET; a chat turn is a POST with the
 * visitor's message in the body, so the stream is read off `fetch` instead. Only `event:` and
 * `data:` are honoured — `id:` and `retry:` matter for reconnection, which a single turn never
 * does — and comment lines (`:`), used as keep-alives, are skipped.
 */
export type ServerSentEvent = {
    event: string;
    data: string;
};

export async function readEventStream(
    response: Response,
    onEvent: (event: ServerSentEvent) => void,
): Promise<void> {
    if (!response.body) {
        return;
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let eventName = 'message';
    let dataLines: string[] = [];

    const dispatch = (): void => {
        if (dataLines.length > 0) {
            onEvent({ event: eventName, data: dataLines.join('\n') });
        }

        eventName = 'message';
        dataLines = [];
    };

    const consumeLine = (line: string): void => {
        if (line === '') {
            dispatch();

            return;
        }

        if (line.startsWith(':')) {
            return;
        }

        const colon = line.indexOf(':');
        const field = colon === -1 ? line : line.slice(0, colon);
        const value =
            colon === -1 ? '' : line.slice(colon + 1).replace(/^ /, '');

        if (field === 'event') {
            eventName = value;
        } else if (field === 'data') {
            dataLines.push(value);
        }
    };

    for (;;) {
        const { done, value } = await reader.read();

        buffer += decoder.decode(value, { stream: !done });

        // A `\r` closing a chunk may be the first half of a `\r\n`; it waits for the next one.
        const end =
            !done && buffer.endsWith('\r') ? buffer.length - 1 : buffer.length;
        const lines = buffer.slice(0, end).split(/\r\n|\r|\n/);
        buffer = done ? '' : (lines.pop() ?? '') + buffer.slice(end);
        lines.forEach(consumeLine);

        if (done) {
            dispatch();

            return;
        }
    }
}
