"use client";

import * as React from "react";
import * as TabsPrimitive from "@radix-ui/react-tabs";

import { cn } from "./utils";

function Tabs({
  className,
  ...props
}: React.ComponentProps<typeof TabsPrimitive.Root>) {
  return (
    <TabsPrimitive.Root
      data-slot="tabs"
      className={cn("flex flex-col gap-2", className)}
      {...props}
    />
  );
}

function TabsList({
  className,
  ...props
}: React.ComponentProps<typeof TabsPrimitive.List>) {
  return (
    <TabsPrimitive.List
      data-slot="tabs-list"
      className={cn(
        "inline-flex h-10 w-fit items-center gap-1 border-b-2 border-border bg-transparent",
        className,
      )}
      {...props}
    />
  );
}

function TabsTrigger({
  className,
  ...props
}: React.ComponentProps<typeof TabsPrimitive.Trigger>) {
  return (
    <TabsPrimitive.Trigger
      data-slot="tabs-trigger"
      className={cn(
        // Base styles - more rectangular tabs with subtle background
        "inline-flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-medium whitespace-nowrap transition-all",
        // Inactive state - lighter background for better contrast
        "bg-muted/50 text-muted-foreground border border-border border-b-0 rounded-t-md",
        // Hover state
        "hover:bg-muted hover:text-foreground",
        // Active state - distinct visual treatment
        "data-[state=active]:bg-card data-[state=active]:text-primary data-[state=active]:border-border data-[state=active]:border-b-2 data-[state=active]:border-b-card data-[state=active]:-mb-[2px] data-[state=active]:shadow-sm",
        // Dark mode adjustments
        "dark:data-[state=active]:bg-card dark:data-[state=active]:text-primary dark:bg-muted/30 dark:hover:bg-muted/50",
        // Focus and disabled states
        "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
        "disabled:pointer-events-none disabled:opacity-50",
        // Icon sizing
        "[&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
        className,
      )}
      {...props}
    />
  );
}

function TabsContent({
  className,
  ...props
}: React.ComponentProps<typeof TabsPrimitive.Content>) {
  return (
    <TabsPrimitive.Content
      data-slot="tabs-content"
      className={cn("flex-1 outline-none", className)}
      {...props}
    />
  );
}

export { Tabs, TabsList, TabsTrigger, TabsContent };
