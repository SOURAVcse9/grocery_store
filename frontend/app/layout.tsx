import type { Metadata } from 'next';
import { Inter } from 'next/font/google';
import '@/styles/globals.css';
import { Header } from '@/components/Header';
import { Footer } from '@/components/Footer';
import { getCategories } from '@/lib/api/categories';
import { JsonLd } from '@/components/JsonLd';

const inter = Inter({ subsets: ['latin'] });

export const metadata: Metadata = {
  title: 'GroCo Grocery Store — Fresh Produce & Daily Essentials Online',
  description: 'Order 100% farm-fresh vegetables, organic fruits, dairy, and grocery staples online with fast doorstep delivery in Bangladesh.',
  metadataBase: new URL('http://localhost:8080'),
  openGraph: {
    title: 'GroCo Grocery Store',
    description: 'Fast, reliable online supermarket in Bangladesh.',
    url: 'http://localhost:8080',
    siteName: 'GroCo Grocery Store',
    type: 'website',
  },
};

export default async function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  let categories: any[] = [];
  try {
    const res = await getCategories();
    categories = res.data || [];
  } catch (err) {
    categories = [];
  }

  const organizationSchema = {
    '@context': 'https://schema.org',
    '@type': 'GroceryStore',
    name: 'GroCo Grocery Store',
    url: 'http://localhost:8080',
    description: 'Premier online grocery supermarket in Bangladesh.',
    telephone: '+880 1700-000000',
    priceRange: '৳৳',
    currenciesAccepted: 'BDT',
    paymentAccepted: 'Cash, Mobile Banking, Credit Card',
  };

  return (
    <html lang="en">
      <head>
        <JsonLd data={organizationSchema} />
      </head>
      <body className={inter.className}>
        <div className="min-h-screen flex flex-col justify-between">
          <Header categories={categories} />
          <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
            {children}
          </main>
          <Footer />
        </div>
      </body>
    </html>
  );
}
