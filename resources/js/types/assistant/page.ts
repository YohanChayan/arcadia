/** The editor's assistant test screen, as `AssistantController` renders it. */
export type AssistantPageProps = {
    provider: string;
    models: { planner: string; speaker: string };
    hasKey: boolean;
    cacheInstructions: boolean;
    maxLength: number;
};
