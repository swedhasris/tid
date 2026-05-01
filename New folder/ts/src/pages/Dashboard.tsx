import React, { useEffect, useState } from "react";
import { collection, query, onSnapshot, where, limit } from "firebase/firestore";
import { db, handleFirestoreError, OperationType } from "../lib/firebase";
import { useAuth } from "../contexts/AuthContext";
import { 
  RotateCcw, 
  Info, 
  Edit2, 
  MoreVertical, 
  ChevronDown,
  HelpCircle,
  MessageSquare,
  Ticket as TicketIcon,
  Clock,
  Search,
  ChevronLeft
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Link } from "react-router-dom";

export function Dashboard() {
  const { user, profile } = useAuth();
  const [searchTerm, setSearchTerm] = useState("");
  const [dashboardStats, setDashboardStats] = useState({
    notViewedYear: 0,
    notViewedSixMonths: 0,
    deactivated30Days: 0,
    updatedLast30Days: 4,
  });

  const dashboards = [
    { id: 1, name: "Analytics Usage Overview", description: "", active: true, views: 0 },
    { id: 2, name: "Application Services Dashboard", description: "", active: true, views: 0 },
    { id: 3, name: "Asset Overview", description: "", active: true, views: 0 },
    { id: 4, name: "Change Request", description: "View the change request workflows status", active: true, views: 0 },
    { id: 5, name: "Data Classification", description: "Data classification dashboard", active: true, views: 0 },
    { id: 6, name: "Gen AI Actions Dashboard", description: "", active: true, views: 0 },
    { id: 7, name: "Guided Tours - Operational Dashboard", description: "The Guided Tours Overview dashboard provides visibility...", active: true, views: 0 },
    { id: 8, name: "Incident Management", description: "View the process metrics for Open and Resolved incidents", active: true, views: 0 },
    { id: 9, name: "Instance Scan Results Next Experience Dashboard", description: "Instance Scan Results Next Experience Dashboard", active: true, views: 0 },
    { id: 10, name: "Interaction", description: "View the distribution and process metrics for interactions", active: true, views: 0 },
  ];

  return (
    <div className="space-y-6 max-w-7xl mx-auto py-6">
      <div className="flex items-center justify-between pb-4 border-b border-border">
        <div className="flex items-center gap-2">
           <h1 className="text-xl font-bold text-sn-dark">Dashboards</h1>
           <Button variant="ghost" size="icon" className="h-6 w-6"><Info className="w-3.5 h-3.5 text-muted-foreground" /></Button>
        </div>
        <div className="flex items-center gap-2">
           <Button className="bg-sn-green text-sn-dark font-bold h-8">Create dashboard</Button>
        </div>
      </div>

      {/* Stats Cards Section */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        {[
          { label: "Not viewed in 1 year", value: dashboardStats.notViewedYear, icon: <RotateCcw className="w-4 h-4" /> },
          { label: "Not viewed in the last 6 months", value: dashboardStats.notViewedSixMonths, icon: <Clock className="w-4 h-4" /> },
          { label: "Deactivated for more than 30 days", value: dashboardStats.deactivated30Days, icon: <HelpCircle className="w-4 h-4" /> },
          { label: "Updated in the last 30 days", value: dashboardStats.updatedLast30Days, icon: <Edit2 className="w-4 h-4" /> },
        ].map((card, i) => (
          <div key={i} className="bg-white border border-border rounded-lg p-5 shadow-sm space-y-3">
             <div className="text-[11px] font-bold text-muted-foreground uppercase tracking-wider">{card.label}</div>
             <div className="flex items-center gap-2">
               <span className="text-3xl font-light text-sn-dark">{card.value}</span>
               {i === 3 && <div className="p-1 bg-sn-green/10 text-sn-green rounded"><Info className="w-3 h-3" /></div>}
             </div>
          </div>
        ))}
      </div>

      {/* Main Table Section */}
      <div className="bg-white border border-border rounded-lg shadow-sm">
        <div className="p-4 border-b border-border flex items-center justify-between">
          <div className="flex items-center gap-4">
             <span className="text-sm font-bold">Count <span className="ml-1 px-1.5 py-0.5 bg-muted rounded text-[11px]">10</span></span>
             <div className="w-px h-6 bg-border mx-2" />
             <div className="flex items-center gap-2">
                <span className="text-[11px] font-bold text-muted-foreground uppercase">Filter by</span>
                <select className="p-1 border border-border rounded text-[11px] h-7 bg-muted/30">
                  <option>Category</option>
                </select>
                <select className="p-1 border border-border rounded text-[11px] h-7 bg-muted/30">
                  <option>Select</option>
                </select>
             </div>
          </div>
          <div className="flex items-center gap-4">
             <div className="relative">
                <Search className="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
                <input 
                  type="text" 
                  placeholder="Search by dashboard name or owner"
                  className="pl-8 pr-4 py-1.5 border border-border rounded text-xs w-64 focus:ring-1 focus:ring-sn-green outline-none"
                />
             </div>
             <div className="flex gap-1">
                <Button variant="outline" size="sm" className="h-7 px-3 text-[11px] font-bold">Delete</Button>
                <Button variant="outline" size="sm" className="h-7 px-3 text-[11px] font-bold">Deactivate</Button>
             </div>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-muted/10 border-b border-border">
                <th className="p-3 text-[10px] font-bold uppercase text-muted-foreground tracking-tight w-10">
                  <input type="checkbox" className="w-3 h-3 h-8" />
                </th>
                <th className="p-3 text-[10px] font-bold uppercase text-muted-foreground tracking-tight">Name</th>
                <th className="p-3 text-[10px] font-bold uppercase text-muted-foreground tracking-tight">Description</th>
                <th className="p-3 text-[10px] font-bold uppercase text-muted-foreground tracking-tight">Active</th>
                <th className="p-3 text-[10px] font-bold uppercase text-muted-foreground tracking-tight">Views</th>
              </tr>
              <tr className="border-b border-border">
                <td className="p-2"></td>
                <td className="p-2"><input placeholder="Search" className="w-full p-1 border border-border rounded text-[11px] outline-none focus:ring-1 focus:ring-sn-green" /></td>
                <td className="p-2"><input placeholder="Search" className="w-full p-1 border border-border rounded text-[11px] outline-none focus:ring-1 focus:ring-sn-green" /></td>
                <td className="p-2"><input placeholder="Search" className="w-full p-1 border border-border rounded text-[11px] outline-none focus:ring-1 focus:ring-sn-green" /></td>
                <td className="p-2"><input placeholder="Search" className="w-full p-1 border border-border rounded text-[11px] outline-none focus:ring-1 focus:ring-sn-green" /></td>
              </tr>
            </thead>
            <tbody>
              {dashboards.map((db, idx) => (
                <tr key={db.id} className="border-b border-border hover:bg-muted/5 transition-colors">
                  <td className="p-3"><input type="checkbox" className="w-3 h-3 h-8" /></td>
                  <td className="p-3 text-[11px] font-bold text-blue-600 cursor-pointer hover:underline">{db.name}</td>
                  <td className="p-3 text-[11px] text-muted-foreground max-w-xs truncate">{db.description || ""}</td>
                  <td className="p-3">
                    <div className="flex items-center gap-2">
                       <div className="w-8 h-4 bg-sn-green rounded-full relative cursor-pointer">
                         <div className="absolute right-0.5 top-0.5 w-3 h-3 bg-white rounded-full" />
                       </div>
                       <span className="text-[10px] font-bold text-sn-green">True</span>
                    </div>
                  </td>
                  <td className="p-3 text-[11px]">{db.views}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        
        <div className="p-3 border-t border-border flex items-center justify-between text-[11px]">
           <div className="text-muted-foreground">Showing 1-10 of 10</div>
           <div className="flex items-center gap-4">
              <div className="flex items-center gap-1">
                 <Button variant="ghost" size="sm" className="h-6 w-6 p-0"><ChevronLeft className="w-3 h-3" /></Button>
                 <span className="px-2 font-bold bg-muted rounded">1</span>
                 <Button variant="ghost" size="sm" className="h-6 w-6 p-0 disabled:opacity-30">2</Button>
                 <Button variant="ghost" size="sm" className="h-6 w-6 p-0 disabled:opacity-30">3</Button>
                 <Button variant="ghost" size="sm" className="h-6 w-6 p-0"><MoreVertical className="w-3 h-3 rotate-90" /></Button>
              </div>
              <div className="text-muted-foreground">Records per page: 20</div>
           </div>
        </div>
      </div>
    </div>
  );
}

