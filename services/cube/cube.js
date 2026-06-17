// CallLens Cube configuration. Enforces tenant isolation on EVERY query by
// injecting a tenant_id filter from the signed security context (spec §7.2/§14).
module.exports = {
  queryRewrite: (query, { securityContext }) => {
    const tenantId = securityContext && securityContext.tenantId;
    if (!tenantId) {
      throw new Error('Cube: missing tenantId in security context');
    }

    const members = [
      ...(query.measures || []),
      ...(query.dimensions || []),
      ...((query.timeDimensions || []).map((t) => t.dimension)),
      ...((query.filters || []).map((f) => f.member).filter(Boolean)),
    ];
    const cubes = new Set(members.map((m) => String(m).split('.')[0]));

    query.filters = query.filters || [];
    for (const cube of ['calls', 'call_scores', 'agents']) {
      if (cubes.has(cube)) {
        query.filters.push({ member: `${cube}.tenant_id`, operator: 'equals', values: [tenantId] });
      }
    }

    return query;
  },
};
