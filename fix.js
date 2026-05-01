const fs = require('fs');
const file = 'src/pages/TimesheetReports.tsx';
let content = fs.readFileSync(file, 'utf8');
const search = '{ts.submittedAt ? new Date(ts.submittedAt.seconds * 1000).toLocaleDateString() : "—"}';
const replace = '{ts.submittedAt ? (ts.submittedAt.seconds ? new Date(ts.submittedAt.seconds * 1000).toLocaleDateString() : new Date(typeof ts.submittedAt === "string" || typeof ts.submittedAt === "number" ? ts.submittedAt : ts.submittedAt.toDate?.() || ts.submittedAt).toLocaleDateString()) : "—"}';
content = content.replace(search, replace);
fs.writeFileSync(file, content);
console.log("Done");
