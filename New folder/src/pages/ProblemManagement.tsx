import React, { useState } from "react";
import { 
  AlertOctagon, 
  Search, 
  Filter, 
  Plus, 
  ChevronRight,
  Clock,
  User,
  Activity
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const MOCK_PROBLEMS = [
  { id: "PRB001", title: "Recurring VPN Disconnections", status: "Root Cause Analysis", priority: "High", assignedTo: "Network Team", incidents: 12, createdAt: "2026-04-10" },
  { id: "PRB002", title: "Slow ERP Performance during Peak Hours", status: "Workaround Provided", priority: "Critical", assignedTo: "App Support", incidents: 45, createdAt: "2026-04-12" },
  { id: "PRB003", title: "Email Delivery Delays to External Domains", status: "New", priority: "Medium", assignedTo: "Messaging Team", incidents: 8, createdAt: "2026-04-15" },
];

export function ProblemManagement() {
  const [problems] = useState(MOCK_PROBLEMS);

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      <div className="flex items-center justify-between">
        <div className="space-y-1">
          <h1 className="text-3xl font-light text-sn-dark">Problem Management</h1>
          <p className="text-muted-foreground">Identify root causes and prevent recurring incidents.</p>
        </div>
        <Button className="bg-sn-green text-sn-dark font-bold gap-2">
          <Plus className="w-4 h-4" /> Create Problem
        </Button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="sn-card bg-red-50 border-red-100 p-6 flex items-center gap-4">
          <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
            <AlertOctagon className="w-6 h-6 text-red-600" />
          </div>
          <div>
            <div className="text-2xl font-bold text-red-700">3</div>
            <div className="text-xs font-bold uppercase text-red-600/70">Open Problems</div>
          </div>
        </div>
        <div className="sn-card bg-blue-50 border-blue-100 p-6 flex items-center gap-4">
          <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
            <Activity className="w-6 h-6 text-blue-600" />
          </div>
          <div>
            <div className="text-2xl font-bold text-blue-700">65</div>
            <div className="text-xs font-bold uppercase text-blue-600/70">Linked Incidents</div>
          </div>
        </div>
        <div className="sn-card bg-green-50 border-green-100 p-6 flex items-center gap-4">
          <div className="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
            <Clock className="w-6 h-6 text-green-600" />
          </div>
          <div>
            <div className="text-2xl font-bold text-green-700">14d</div>
            <div className="text-xs font-bold uppercase text-green-600/70">Avg. Resolution Time</div>
          </div>
        </div>
      </div>

      <div className="sn-card p-0 overflow-hidden">
        <div className="p-4 border-b border-border bg-muted/10 flex items-center justify-between">
          <div className="flex items-center gap-2 flex-grow max-w-md">
            <div className="relative flex-grow">
              <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <input 
                type="text"
                placeholder="Search problems..."
                className="w-full bg-white border border-border rounded py-1.5 pl-9 pr-3 text-sm outline-none focus:ring-1 focus:ring-sn-green"
              />
            </div>
            <Button variant="outline" size="sm" className="gap-2 border-border">
              <Filter className="w-3.5 h-3.5" /> Filter
            </Button>
          </div>
        </div>

        <div className="divide-y divide-border">
          {problems.map((problem) => (
            <div key={problem.id} className="p-6 hover:bg-muted/10 transition-colors cursor-pointer group">
              <div className="flex items-start justify-between gap-4">
                <div className="space-y-2 flex-grow">
                  <div className="flex items-center gap-3">
                    <span className="text-xs font-mono text-blue-600 font-bold">{problem.id}</span>
                    <span className={cn(
                      "px-2 py-0.5 rounded text-[10px] font-bold uppercase",
                      problem.priority === "Critical" ? "bg-red-100 text-red-700" : "bg-orange-100 text-orange-700"
                    )}>
                      {problem.priority}
                    </span>
                    <span className="px-2 py-0.5 rounded bg-muted text-[10px] font-bold uppercase text-muted-foreground">
                      {problem.status}
                    </span>
                  </div>
                  <h3 className="text-lg font-bold text-sn-dark group-hover:text-sn-green transition-colors">
                    {problem.title}
                  </h3>
                  <div className="flex items-center gap-6 text-xs text-muted-foreground">
                    <div className="flex items-center gap-1.5">
                      <User className="w-3.5 h-3.5" /> {problem.assignedTo}
                    </div>
                    <div className="flex items-center gap-1.5">
                      <AlertOctagon className="w-3.5 h-3.5" /> {problem.incidents} Linked Incidents
                    </div>
                    <div className="flex items-center gap-1.5">
                      <Clock className="w-3.5 h-3.5" /> Created {problem.createdAt}
                    </div>
                  </div>
                </div>
                <ChevronRight className="w-5 h-5 text-muted-foreground group-hover:text-sn-green transition-all" />
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
