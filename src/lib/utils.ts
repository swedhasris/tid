import { clsx, type ClassValue } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function formatDate(date: any): string {
  if (!date) return "—";
  if (typeof date.toDate === "function") {
    return date.toDate().toLocaleDateString();
  }
  if (date instanceof Date) {
    return date.toLocaleDateString();
  }
  if (typeof date === "string") {
    const d = new Date(date);
    return isNaN(d.getTime()) ? "—" : d.toLocaleDateString();
  }
  if (typeof date === "number") {
    return new Date(date).toLocaleDateString();
  }
  if (date.seconds) {
    return new Date(date.seconds * 1000).toLocaleDateString();
  }
  return "—";
}
