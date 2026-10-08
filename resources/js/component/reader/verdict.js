/**
 * The reviewer no longer picks a decision: any remark sends the document back,
 * none approves it. A "Good" remark is praise, so it does not count.
 */
export const verdictFor = (remarks, general) =>
  general.length || remarks.some((r) => r.color !== "green") ? "needs_revision" : "approved";
