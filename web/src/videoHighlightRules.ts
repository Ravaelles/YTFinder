export type HighlightRule = {
  keywords: string[];
  color: string;
};

// Rule order matters: first matching rule is applied.
export const VIDEO_HIGHLIGHT_RULES: HighlightRule[] = [
  { keywords: ["stress", "anxiety", "relax"], color: "#FF7A00" },
  { keywords: ["morning"], color: "#00A3FF" },
  { keywords: ["evening", "bed"], color: "#001E9A" },
  { keywords: ["beginner"], color: "#00C853" },
  { keywords: ["everyday"], color: "#FFD400" },
  { keywords: ["stretch", "advanced"], color: "#FF2D96" }
];
